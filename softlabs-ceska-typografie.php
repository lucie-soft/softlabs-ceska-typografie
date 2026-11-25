<?php
/**
 * Plugin Name: Softlabs – Česká typografie
 * Plugin URI: https://softlabs.cz
 * Description: Hlídá českou typografii: jednopísmenná slova, krátké předložky a spojky, čísla s jednotkami a tituly nezůstanou na konci řádku, pomlčka nezačne řádek a krátké slovo nezůstane samo na konci odstavce.
 * Version: 1.4.2
 * Author: Softlabs
 * Author URI: https://softlabs.cz
 * Requires PHP: 7.4
 * Text Domain: softlabs-ceska-typografie
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright (C) 2026 SOFTLABS CZECH s.r.o.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Softlabs_Ceska_Typografie {

	const OPTION = 'softlabs_ceska_typografie_options';

	/** Předložky (vlastní i nevlastní, nejčastější). */
	const DEFAULT_PREPOSITIONS = 'bez, dle, do, k, ke, ku, na, nad, o, ob, od, ode, po, pod, pro, při, s, se, u, v, ve, vně, z, za, ze';

	/** Spojky souřadicí i podřadicí. */
	const DEFAULT_CONJUNCTIONS = 'a, ač, ale, ani, ať, aby, až, ba, či, i, jak, jen, leč, li, než, pak, že';

	/** Tituly a zkratky, za kterými se neláme. */
	const DEFAULT_ABBREVIATIONS = 'MUDr., MDDr., MVDr., PharmDr., PhDr., Mgr., Ing., Bc., Dr., doc., prof., č., čp., tel., str., tj., tzn., např., sv., p., pí., ul.';

	/** Další slova (např. číslovky slovy). */
	const DEFAULT_CUSTOM = 'dva, dvě, tři, pět, osm';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
	}

	/* ---------- Nastavení ---------- */

	public static function defaults() {
		return array(
			'enabled'       => 1,
			'single'        => 1,
			'numbers'       => 1,
			'dash'          => 1,
			'widow_max'     => 5,
			'exclude_class' => 'bez-nbsp',
			'prepositions'  => self::DEFAULT_PREPOSITIONS,
			'conjunctions'  => self::DEFAULT_CONJUNCTIONS,
			'abbreviations' => self::DEFAULT_ABBREVIATIONS,
			'custom'        => self::DEFAULT_CUSTOM,
		);
	}

	public static function options() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public function register_settings() {
		register_setting(
			'softlabs_ceska_typografie',
			self::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function sanitize( $input ) {
		$out = array();
		foreach ( array( 'enabled', 'single', 'numbers', 'dash' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'prepositions', 'conjunctions', 'abbreviations', 'custom' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? implode( ', ', self::parse_list( $input[ $key ] ) ) : '';
		}
		$out['widow_max']     = isset( $input['widow_max'] ) ? max( 0, min( 20, (int) $input['widow_max'] ) ) : 0;
		$out['exclude_class'] = isset( $input['exclude_class'] ) ? sanitize_html_class( $input['exclude_class'] ) : '';
		return $out;
	}

	private static function parse_list( $text ) {
		$items = preg_split( '/[\s,;]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$items = array_map( 'sanitize_text_field', $items );
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/* ---------- Úprava výstupu ---------- */

	public function start_buffer() {
		if ( ! $this->should_run() ) {
			return;
		}
		ob_start( array( $this, 'process_buffer' ) );
	}

	private function should_run() {
		$o = self::options();
		if ( empty( $o['enabled'] ) ) {
			return false;
		}
		if ( is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron() || is_customize_preview() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Divi Visual Builder a náhledy builderu.
		if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_pb_preview'] ) || isset( $_GET['et_tb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		return (bool) apply_filters( 'softlabs_ceska_typografie_enabled', true );
	}

	public function process_buffer( $html ) {
		if ( '' === $html || ! $this->is_html_response() ) {
			return $html;
		}
		$processed = self::process_html( $html );
		return ( null === $processed ) ? $html : $processed;
	}

	private function is_html_response() {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'Content-Type:' ) ) {
				return false !== stripos( $header, 'text/html' );
			}
		}
		return true;
	}

	/**
	 * Projde HTML a upraví jen textové uzly mimo <head>, <script>, <style>, <textarea>, <pre>, <code>, <svg>
	 * a mimo prvky s výjimkovou CSS třídou (výchozí „bez-nbsp“).
	 */
	public static function process_html( $html ) {
		$o        = self::options();
		$patterns = self::patterns();
		$widow    = (int) $o['widow_max'];

		$parts = self::tokenize( $html );
		if ( null === $parts ) {
			return null;
		}

		$skip_tags = 'head|script|style|textarea|pre|code|svg|noscript|template|title';
		$void      = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' );
		$skip      = 0;
		$exclude   = trim( (string) $o['exclude_class'] );
		$ex_tag    = '';
		$ex_depth  = 0;
		$count     = count( $parts );

		for ( $i = 0; $i < $count; $i++ ) {
			$part = $parts[ $i ];
			if ( '' === $part ) {
				continue;
			}

			if ( '<' === $part[0] ) {
				if ( 0 === strpos( $part, '<!--' ) ) {
					continue;
				}
				// Celý blok <script>…</script> nebo <style>…</style> – nic v něm neměníme.
				if ( preg_match( '/^<(script|style)\b.*<\/\1\s*>$/si', $part ) ) {
					continue;
				}
				if ( preg_match( '/^<(\/?)(' . $skip_tags . ')\b[^>]*?(\/?)>$/i', $part, $m ) ) {
					if ( '/' === $m[1] ) {
						$skip = max( 0, $skip - 1 );
					} elseif ( '/' !== $m[3] ) {
						$skip++;
					}
				}
				// Výjimka podle CSS třídy – sledujeme vnoření stejného tagu.
				if ( preg_match( '/^<(\/?)([a-z][a-z0-9-]*)\b/i', $part, $t ) ) {
					$name = strtolower( $t[2] );
					if ( '' !== $ex_tag ) {
						if ( $name === $ex_tag && ! in_array( $name, $void, true ) && '/>' !== substr( $part, -2 ) ) {
							$ex_depth += ( '/' === $t[1] ) ? -1 : 1;
							if ( $ex_depth <= 0 ) {
								$ex_tag   = '';
								$ex_depth = 0;
							}
						}
					} elseif ( '' !== $exclude && '/' !== $t[1] && ! in_array( $name, $void, true )
						&& preg_match( '/\bclass\s*=\s*(["\'])[^"\']*(?<![\w-])' . preg_quote( $exclude, '/' ) . '(?![\w-])[^"\']*\1/i', $part ) ) {
						$ex_tag   = $name;
						$ex_depth = 1;
					}
				}
				continue;
			}

			if ( $skip > 0 || '' !== $ex_tag || '' === trim( $part ) ) {
				continue;
			}

			$new = $patterns ? preg_replace( array_keys( $patterns ), array_values( $patterns ), $part ) : $part;
			if ( null === $new ) {
				continue;
			}

			// Krátké poslední slovo odstavce (<p>) nezůstane samo na řádku.
			if ( $widow > 0 && self::ends_paragraph( $parts, $i ) ) {
				$w = preg_replace(
					'/(\S)(?:[ \t\r\n]|&nbsp;|\x{00A0})+([\p{L}\p{N}][\p{L}\p{N}\-]{0,' . ( $widow - 1 ) . '}[.,!?…:;)"“”»]*)[ \t\r\n]*$/u',
					'$1&nbsp;$2',
					$new
				);
				if ( null !== $w ) {
					$new = $w;
				}
			}

			$parts[ $i ] = $new;
		}

		return implode( '', $parts );
	}

	/**
	 * Rozdělí HTML na textové uzly a značky.
	 * Bloky <script>…</script> a <style>…</style> vrací vcelku jako jednu položku: jejich obsah může
	 * obsahovat „<“ a „>“ (např. kritické CSS z FlyingPress: @media (width<=980px)) a nesmí se
	 * rozdělit na značky. Hledají se přes stripos, takže nenarazí na limit PCRE ani u velkých bloků.
	 */
	private static function tokenize( $html ) {
		$parts = array();
		$pos   = 0;

		while ( preg_match( '/<(script|style)\b/i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$start = $m[0][1];
			$close = stripos( $html, '</' . strtolower( $m[1][0] ), $start + 1 );
			$end   = ( false === $close ) ? false : strpos( $html, '>', $close );
			if ( false === $end ) {
				break;
			}
			$end++;
			$chunk = self::split_tags( substr( $html, $pos, $start - $pos ) );
			if ( null === $chunk ) {
				return null;
			}
			$parts   = array_merge( $parts, $chunk );
			$parts[] = substr( $html, $start, $end - $start );
			$pos     = $end;
		}

		$chunk = self::split_tags( substr( $html, $pos ) );
		return ( null === $chunk ) ? null : array_merge( $parts, $chunk );
	}

	/** Rozdělí úsek HTML na text a značky. Hodnoty atributů v uvozovkách mohou obsahovat „>“. */
	private static function split_tags( $html ) {
		if ( '' === $html ) {
			return array();
		}
		$parts = preg_split(
			'/(<!--.*?-->|<(?:[^>"\']|"[^"]*"|\'[^\']*\')*+>)/su',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		return ( false === $parts ) ? null : $parts;
	}

	/** Následuje za textovým uzlem (případně přes uzavírací inline tagy) konec odstavce </p>? */
	private static function ends_paragraph( $parts, $i ) {
		$count = count( $parts );
		for ( $j = $i + 1; $j < $count; $j++ ) {
			$p = $parts[ $j ];
			if ( '' === $p ) {
				continue;
			}
			if ( '<' !== $p[0] ) {
				if ( '' === trim( $p ) ) {
					continue;
				}
				return false;
			}
			if ( preg_match( '/^<\/p\s*>$/i', $p ) ) {
				return true;
			}
			if ( preg_match( '/^<\/(strong|b|em|i|a|span|small|mark|u|sup|sub)\s*>$/i', $p ) ) {
				continue;
			}
			return false;
		}
		return false;
	}
	/**
	 * Regulární výrazy: za slovem (číslem) nahradí běžnou mezeru nezalomitelnou.
	 * Před slovem nesmí být písmeno ani číslice, za mezerou musí něco následovat (nebo konec textového uzlu před tagem).
	 */
	private static function patterns() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$o        = self::options();
		$cache    = array();
		$before   = '(?<![\p{L}\p{N}&#\-])';
		$space    = '(?:[ \t\r\n]|&nbsp;|\x{00A0})+';
		$after    = '(?=\S|$)';
		$nbsp     = '&nbsp;';

		$words = array_merge(
			self::parse_list( $o['prepositions'] ),
			self::parse_list( $o['conjunctions'] ),
			self::parse_list( $o['custom'] )
		);
		if ( $words ) {
			usort( $words, function ( $a, $b ) {
				return mb_strlen( $b ) - mb_strlen( $a );
			} );
			$alt = implode( '|', array_map( function ( $w ) {
				return preg_quote( $w, '/' );
			}, $words ) );
			$cache[ '/' . $before . '(' . $alt . ')' . $space . $after . '/iu' ] = '$1' . $nbsp;
		}

		$abbr = self::parse_list( $o['abbreviations'] );
		if ( $abbr ) {
			$alt = implode( '|', array_map( function ( $w ) {
				return preg_quote( $w, '/' );
			}, $abbr ) );
			$cache[ '/' . $before . '(' . $alt . ')' . $space . $after . '/u' ] = '$1' . $nbsp;
		}

		if ( ! empty( $o['single'] ) ) {
			$cache[ '/' . $before . '(\p{L})' . $space . $after . '/u' ] = '$1' . $nbsp;
		}

		if ( ! empty( $o['numbers'] ) ) {
			// Číslo (i s desetinnou čárkou, tečkou nebo dvojtečkou, např. 3, 2,5, 9:30) se nespojí s následujícím slovem/jednotkou.
			$cache[ '/' . $before . '(\d+(?:[.,:]\d+)*\.?)' . $space . $after . '/u' ] = '$1' . $nbsp;
		}

		if ( ! empty( $o['dash'] ) ) {
			// Pomlčka (–, —, i jako HTML entita) nezačne nový řádek: mezeru před ní nahradí nezalomitelná.
			$cache['/(?<=\S)[ \t\r\n]+(?=(?:[\x{2013}\x{2014}]|&#821[12];|&#x201[34];|&[nm]dash;)(?:[ \t\r\n]|&nbsp;|$))/iu'] = $nbsp;
		}

		return $cache;
	}

	/* ---------- Administrace ---------- */

	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=softlabs-ceska-typografie' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Nastavení</a>' );
		return $links;
	}

	public function admin_menu() {
		add_options_page(
			'Česká typografie',
			'Česká typografie',
			'manage_options',
			'softlabs-ceska-typografie',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) && 'navod' === $_GET['tab'] ? 'navod' : 'nastaveni'; // phpcs:ignore WordPress.Security.NonceVerification
		$base = admin_url( 'options-general.php?page=softlabs-ceska-typografie' );
		?>
		<div class="wrap">
			<h1>Česká typografie</h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base ); ?>" class="nav-tab <?php echo 'nastaveni' === $tab ? 'nav-tab-active' : ''; ?>">Nastavení</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'navod', $base ) ); ?>" class="nav-tab <?php echo 'navod' === $tab ? 'nav-tab-active' : ''; ?>">Návod</a>
			</nav>
			<?php
			if ( 'navod' === $tab ) {
				$this->render_guide();
			} else {
				$this->render_settings();
			}
			?>
		</div>
		<?php
	}

	private function render_settings() {
		$o    = self::options();
		$name = self::OPTION;
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'softlabs_ceska_typografie' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Zapnuto</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $o['enabled'] ); ?>> Upravovat text na webu</label></td>
				</tr>
				<tr>
					<th scope="row">Jednopísmenná slova</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[single]" value="1" <?php checked( $o['single'] ); ?>> Každé jednopísmenné slovo (a, i, k, s, v, z, o, u, A, I…)</label></td>
				</tr>
				<tr>
					<th scope="row">Čísla</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[numbers]" value="1" <?php checked( $o['numbers'] ); ?>> Číslo zůstane na řádku s následujícím slovem (3&nbsp;měsíce, 2&nbsp;pracovních dnů, 9:30&nbsp;h)</label></td>
				</tr>
				<tr>
					<th scope="row">Pomlčka</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[dash]" value="1" <?php checked( $o['dash'] ); ?>> Pomlčka nezačne nový řádek (9:00&nbsp;– 17:00, Ordinace&nbsp;– objednávky)</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-widow">Poslední slovo odstavce</label></th>
					<td><input type="number" id="sl-widow" min="0" max="20" class="small-text" name="<?php echo esc_attr( $name ); ?>[widow_max]" value="<?php echo esc_attr( $o['widow_max'] ); ?>"> znaků
					<p class="description">Poslední slovo odstavce s tímto nebo menším počtem znaků se nezalomí samo na nový řádek. 0 = vypnuto.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-exclude">Výjimka (CSS třída)</label></th>
					<td><input type="text" id="sl-exclude" class="regular-text" name="<?php echo esc_attr( $name ); ?>[exclude_class]" value="<?php echo esc_attr( $o['exclude_class'] ); ?>">
					<p class="description">Prvky s touto třídou (i všechno uvnitř) plugin neupravuje.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-prep">Předložky</label></th>
					<td><textarea id="sl-prep" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[prepositions]"><?php echo esc_textarea( $o['prepositions'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-conj">Spojky</label></th>
					<td><textarea id="sl-conj" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[conjunctions]"><?php echo esc_textarea( $o['conjunctions'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-abbr">Tituly a zkratky</label></th>
					<td><textarea id="sl-abbr" class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>[abbreviations]"><?php echo esc_textarea( $o['abbreviations'] ); ?></textarea>
					<p class="description">Rozlišují se velká a malá písmena (MUDr.&nbsp;Novák).</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-custom">Další slova</label></th>
					<td><textarea id="sl-custom" class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>[custom]"><?php echo esc_textarea( $o['custom'] ); ?></textarea>
					<p class="description">Cokoli dalšího, co nemá zůstat na konci řádku (např. číslovky slovy).</p></td>
				</tr>
			</table>
			<?php submit_button( 'Uložit nastavení' ); ?>
		</form>
		<?php
	}

	private function render_guide() {
		?>
		<div style="max-width:760px">
			<h2>Co plugin dělá</h2>
			<p>Při zobrazení stránky návštěvníkovi nahradí mezeru za vybranými slovy nezalomitelnou mezerou. Slovo se pak vždy přesune na další řádek spolu s tím, co za ním následuje, a nezůstane osamocené na konci řádku.</p>
			<p>Hlídá:</p>
			<ul style="list-style:disc;padding-left:20px">
				<li>všechna jednopísmenná slova (a, i, k, s, v, z, o, u),</li>
				<li>krátké předložky a spojky ze seznamu (do, na, pro, ale, že…),</li>
				<li>čísla (3&nbsp;měsíce, 2&nbsp;pracovní dny),</li>
				<li>tituly a zkratky (MUDr.&nbsp;Novák, tel.&nbsp;374…),</li>
				<li>pomlčku – nezačne nový řádek,</li>
				<li>krátké poslední slovo odstavce (výchozí do 5 znaků) – nezůstane samo na řádku,</li>
				<li>další slova, která si doplníte.</li>
			</ul>

			<h2>Jak plnit obsah</h2>
			<p>Nic zvláštního dělat nemusíte. Pište texty v Divi i ve WordPressu normálně s obyčejnými mezerami, plugin je upraví sám až při zobrazení. V administraci ani v Divi Builderu se nic nemění.</p>

			<h2>Úprava seznamů</h2>
			<p>V záložce <strong>Nastavení</strong> můžete slova přidávat a mazat. Oddělujte je čárkou nebo mezerou. U předložek, spojek a dalších slov nezáleží na velikosti písmen, u titulů a zkratek ano.</p>

			<h2>Výjimka pro konkrétní místo</h2>
			<p>Pokud se nezalomitelné mezery někde nehodí (úzká tabulka, tlačítko), dejte modulu v Divi CSS třídu <code>bez-nbsp</code> (Pokročilé → CSS ID a třídy). Plugin pak tento modul i všechno uvnitř přeskočí. Název třídy lze změnit v Nastavení.</p>

			<h2>Po změně nastavení</h2>
			<p>Pokud web používá cache (např. FlyingPress), smažte ji, aby se změna projevila všem návštěvníkům.</p>

			<h2>Shortcody</h2>
			<p>Plugin žádné shortcody nemá, funguje automaticky na celém webu.</p>

			<h2>Kde plugin nezasahuje</h2>
			<p>Administrace, Divi Builder, kód stránky (skripty, styly), formulářová pole (textarea), RSS, REST API a prvky s výjimkovou třídou.</p>

			<h2>Autor a kontakt</h2>
			<p>Plugin vytvořil tým Softlabs a je zdarma ke stažení pod licencí GPL. Pokud potřebujete poradit s nastavením nebo chcete na webu upravit něco dalšího, napište nám.</p>
			<p>Tým Softlabs<br>
			<a href="mailto:weby@softlabs.cz">weby@softlabs.cz</a><br>
			<a href="https://softlabs.cz" target="_blank" rel="noopener">softlabs.cz</a></p>
		</div>
		<?php
	}
}

Softlabs_Ceska_Typografie::instance();
