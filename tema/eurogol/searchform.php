<?php
defined( 'ABSPATH' ) || exit;
$eg_id = wp_unique_id( 'eg-s-' );
?>
<form role="search" method="get" class="eg-form-cautare" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="eg-ascuns" for="<?php echo esc_attr( $eg_id ); ?>">Caută pe site</label>
	<input type="search" id="<?php echo esc_attr( $eg_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Caută o echipă, un jucător, o competiție…">
	<button type="submit">Caută</button>
</form>
