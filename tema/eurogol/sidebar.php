<?php defined( 'ABSPATH' ) || exit; ?>
<aside class="eg-coloana" aria-label="Coloana laterală">
	<?php
	eg_pe_scurt();
	eg_povesti_lista();
	$retele = eg_retele();
	if ( $retele ) {
		echo '<section class="eg-bloc"><h2 class="eg-titlu-sectiune">Urmărește-ne</h2>' . $retele . '</section>'; // phpcs:ignore
	}
	eg_lista_competitii();
	if ( is_active_sidebar( 'coloana' ) ) {
		dynamic_sidebar( 'coloana' );
	}
	?>
</aside>
