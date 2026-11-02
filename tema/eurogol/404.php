<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="eg-doua-coloane">
	<div class="eg-continut">
		<div class="eg-articol eg-404">
			<p class="eg-404-scor">4<span>-</span>0<span>-</span>4</p>
			<h1 class="eg-articol-titlu">Mingea a ieșit în afara terenului</h1>
			<p>Pagina pe care o cauți nu există sau a fost mutată. Încearcă o căutare sau întoarce-te la <a href="<?php echo esc_url( home_url( '/' ) ); ?>">prima pagină</a>.</p>
			<?php get_search_form(); ?>
		</div>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
