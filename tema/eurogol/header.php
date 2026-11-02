<?php defined( 'ABSPATH' ) || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?php echo esc_attr( get_theme_mod( 'eg_culoare', '#1546A8' ) ); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="eg-sari" href="#continut">Sari la conținut</a>

<header class="eg-antet">
	<div class="eg-antet-sus">
		<div class="eg-latime eg-antet-rand">
			<button class="eg-buton-icon eg-deschide-meniu" aria-controls="eg-nav" aria-expanded="false" aria-label="Meniu"><span class="eg-i-meniu"><?php echo eg_icon( 'meniu' ); // phpcs:ignore ?></span><span class="eg-i-inchide"><?php echo eg_icon( 'inchide' ); // phpcs:ignore ?></span></button>
			<a class="eg-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> – prima pagină">
				<?php
				if ( has_custom_logo() ) {
					echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'alt' => get_bloginfo( 'name' ) ) );
				} else {
					echo '<span class="eg-logo-minge" aria-hidden="true"></span><span class="eg-logo-text">euro<b>gol</b></span>';
				}
				?>
			</a>
			<nav id="eg-nav" class="eg-nav" aria-label="Meniul principal">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'principal',
						'container'      => false,
						'menu_class'     => 'eg-meniu',
						'depth'          => 2,
						'fallback_cb'    => 'eg_meniu_rezerva',
					)
				);
				?>
			</nav>
			<button class="eg-buton-icon eg-deschide-cautare" aria-controls="eg-cautare" aria-expanded="false" aria-label="Caută"><?php echo eg_icon( 'cauta' ); // phpcs:ignore ?></button>
		</div>
		<div id="eg-cautare" class="eg-cautare" hidden>
			<div class="eg-latime"><?php get_search_form(); ?></div>
		</div>
	</div>
	<div class="eg-banda">
		<div class="eg-latime eg-antet-rand">
			<p class="eg-slogan"><?php echo esc_html( get_theme_mod( 'eg_slogan', 'Fotbal românesc, european și sud-american, spus ca o poveste' ) ); ?></p>
			<p class="eg-azi"><?php echo esc_html( eg_data_ro( time(), 'antet' ) ); ?></p>
		</div>
	</div>
</header>

<div class="eg-cutie">
<main id="continut" class="eg-main">
