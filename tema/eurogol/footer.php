<?php defined( 'ABSPATH' ) || exit; ?>
</main>

<footer class="eg-subsol">
	<div class="eg-subsol-negru">
		<p class="eg-copyright">&copy; <?php echo esc_html( wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'subsol',
				'container'      => false,
				'menu_class'     => 'eg-meniu-subsol',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		echo eg_retele(); // phpcs:ignore
		?>
	</div>
	<?php $comp = eg_competitii(); ?>
	<?php if ( $comp ) : ?>
		<div class="eg-subsol-alb">
			<h2>Competiții</h2>
			<ul>
				<?php foreach ( $comp as $c ) : ?>
					<li><a href="<?php echo esc_url( get_category_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</footer>
</div>

<a class="eg-inapoi-sus" href="#" aria-label="Înapoi sus" hidden><?php echo eg_icon( 'sus' ); // phpcs:ignore ?></a>
<?php wp_footer(); ?>
</body>
</html>
