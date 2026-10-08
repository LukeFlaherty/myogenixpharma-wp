<?php
/** Customer stories supplied for the homepage. */
defined( 'ABSPATH' ) || exit;
$stories = [
	[ 'Jordan M.', 'Low energy, inconsistent routine', 'Clear plan, consistent habits', 'I finally felt like I had a plan designed for me—not a one-size-fits-all solution.', 'testosterone', 'TRT evaluation' ],
	[ 'Taylor R.', 'Progress felt stuck', 'Renewed motivation, clear direction', 'The team listened, answered my questions, and helped me stay focused on realistic progress.', 'compound-tirzepatide', 'Tirzepatide' ],
	[ 'Marcus D.', 'Unsure where to start', 'Clarity and confidence', 'I never felt rushed or pressured.', 'testosterone', 'TRT evaluation' ],
	[ 'Alyssa K.', 'Inconsistent progress', 'Personalized, practical goals', 'For the first time, I felt like someone was looking at the full picture.', 'compound-semaglutide', 'Semaglutide' ],
	[ 'Daniel S.', 'Low motivation', 'Better daily consistency', 'Having ongoing guidance kept me accountable.', 'testosterone', 'TRT evaluation' ],
	[ 'Nicole B.', 'Information overload', 'Simple, focused plan', 'I left each conversation knowing exactly what to focus on next.', 'compound-semaglutide', 'Semaglutide' ],
	[ 'Chris W.', 'Too busy for wellness', 'Care that fits', 'The process was smooth, responsive, and respectful of my time.', 'compound-tirzepatide', 'Tirzepatide' ],
	[ 'Samantha L.', 'Uncertain next steps', 'Clear goals, greater confidence', 'They made it comfortable to ask questions and discuss my concerns.', 'compound-semaglutide', 'Semaglutide' ],
	[ 'Andre P.', 'Progress plateau', 'Renewed momentum', 'I wasn’t treated like a number—the team genuinely cared about helping me stay on track.', 'compound-tirzepatide', 'Tirzepatide' ],
	[ 'Megan T.', 'Seeking lasting habits', 'Sustainable habits, dependable guidance', 'The communication was excellent, and the plan felt thoughtful and personalized.', 'compound-semaglutide', 'Semaglutide' ],
];
?>
<section class="grunge-section myo-stories" aria-labelledby="myo-stories-title" aria-roledescription="carousel" data-review-carousel>
	<div class="grunge-container">
		<div class="grunge-section__header grunge-section__header--split">
			<div><p class="grunge-kicker">Customer stories</p><h2 id="myo-stories-title">Personal goals. <span class="grunge-text-red">Meaningful progress.</span></h2><p>A clearer path forward, in their own words.</p></div>
			<div class="myo-stories__controls" hidden>
				<button type="button" data-carousel-prev aria-label="Previous customer story" aria-controls="myo-stories-track">&#8592;</button>
				<button type="button" data-carousel-pause aria-controls="myo-stories-track">Pause</button>
				<button type="button" data-carousel-next aria-label="Next customer story" aria-controls="myo-stories-track">&#8594;</button>
			</div>
		</div>
		<div class="myo-stories__track" id="myo-stories-track" tabindex="0" aria-label="Customer stories; use arrow keys or swipe to browse">
			<?php foreach ( $stories as $index => $story ) : ?>
			<article class="myo-story" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $index + 1 ) . ' of ' . count( $stories ) ); ?>">
				<div class="myo-story__heading"><h3><?php echo esc_html( $story[0] ); ?></h3><span aria-hidden="true">“</span></div>
				<div class="myo-story__before"><span>Before</span><p><?php echo esc_html( $story[1] ); ?></p></div>
				<div class="myo-story__after"><span>After</span><p><?php echo esc_html( $story[2] ); ?></p></div>
				<blockquote>“<?php echo esc_html( $story[3] ); ?>”</blockquote>
				<div class="myo-story__explore"><span>Explore a treatment option</span><a href="<?php echo esc_url( home_url( '/product/' . $story[4] . '/' ) ); ?>"><?php echo esc_html( $story[5] ); ?> <span aria-hidden="true">&#8599;</span></a></div>
			</article>
			<?php endforeach; ?>
		</div>
		<div class="myo-stories__footer"><p>Results vary. Product links are options to discuss with a provider, not treatments attributed to these reviewers.</p><span data-carousel-status aria-live="off">01 / 10</span></div>
	</div>
</section>
