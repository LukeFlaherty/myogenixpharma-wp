<?php
/**
 * Template Name: Home Redesign (Staging)
 *
 * Hidden build page for the in-progress home page redesign. Not linked from
 * navigation, not the live front page. Sections are added here incrementally;
 * once approved, this becomes front-page.php and this file goes away.
 *
 * To revert/remove: delete this file and the WP page using it.
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'myogenix-home-redesign-page' ); ?>>
<?php wp_body_open(); ?>

<?php get_template_part( 'template-parts/site-header-redesign' ); ?>

<main>
	<?php get_template_part( 'template-parts/home-redesign-hero' ); ?>
	<!-- Additional sections are built incrementally as the redesign progresses. -->
</main>

<?php get_template_part( 'template-parts/site-footer' ); ?>

<?php wp_footer(); ?>
</body>
</html>
