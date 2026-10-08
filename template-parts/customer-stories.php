<?php
/** Customer stories supplied for the homepage. */
defined( 'ABSPATH' ) || exit;
$stories = [
	[ 'Jordan M.', 'Low energy and difficulty maintaining a consistent wellness routine.', 'A personalized plan and clearer habits that made staying consistent feel manageable.', 'Myogenix Pharma took the time to understand my goals and explain every step. I finally felt like I had a plan designed for me—not a one-size-fits-all solution.' ],
	[ 'Taylor R.', 'Feeling stuck despite making changes to diet and exercise.', 'Greater accountability, renewed motivation, and a structured path forward.', 'The support made all the difference. The team listened, answered my questions, and helped me stay focused on realistic progress.' ],
	[ 'Marcus D.', 'Uncertainty about which wellness options were appropriate.', 'A clearer understanding of available options and greater confidence in the next steps.', 'I appreciated how straightforward and informative the entire experience was. I never felt rushed or pressured.' ],
	[ 'Alyssa K.', 'Inconsistent progress and frustration with generic programs.', 'A more individualized approach with goals that felt practical and sustainable.', 'For the first time, I felt like someone was looking at the full picture. The team was attentive, professional, and encouraging throughout the process.' ],
	[ 'Daniel S.', 'Low motivation and difficulty following a structured routine.', 'Better consistency and a plan that fit more naturally into everyday life.', 'Myogenix Pharma helped turn a vague goal into clear, manageable steps. Having ongoing guidance kept me accountable.' ],
	[ 'Nicole B.', 'Feeling overwhelmed by conflicting wellness information.', 'A simpler, more focused plan backed by professional guidance.', 'The team made everything easy to understand. I left each conversation knowing exactly what to focus on next.' ],
	[ 'Chris W.', 'A demanding schedule that made personal wellness a low priority.', 'A convenient routine and support system that worked around a busy lifestyle.', 'The process was smooth, responsive, and respectful of my time. I always felt supported without feeling overwhelmed.' ],
	[ 'Samantha L.', 'Difficulty knowing where to begin and concerns about choosing the right approach.', 'Defined goals, clearer expectations, and greater confidence moving forward.', 'Everyone I worked with was kind, knowledgeable, and patient. They made it comfortable to ask questions and discuss my concerns.' ],
	[ 'Andre P.', 'Plateaued progress and a lack of accountability.', 'Renewed momentum, regular support, and a more purposeful routine.', 'What stood out was the personal attention. I wasn’t treated like a number—the team genuinely cared about helping me stay on track.' ],
	[ 'Megan T.', 'Wanting to feel more confident and proactive about long-term wellness.', 'A tailored plan, dependable guidance, and habits that felt easier to maintain.', 'My experience with Myogenix Pharma has been positive from the beginning. The communication was excellent, and the plan felt thoughtful and personalized.' ],
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
			</article>
			<?php endforeach; ?>
		</div>
		<div class="myo-stories__footer"><p>Individual experiences. Results vary.</p><span data-carousel-status aria-live="off">01 / 10</span></div>
	</div>
</section>
