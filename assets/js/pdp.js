document.addEventListener( 'DOMContentLoaded', function () {
	var questions = Array.prototype.slice.call(
		document.querySelectorAll( '.myogenix-pdp__faq-question' )
	);

	questions.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
			var answer     = document.getElementById( this.getAttribute( 'aria-controls' ) );

			// Close all other items first
			questions.forEach( function ( otherBtn ) {
				if ( otherBtn === btn ) return;
				otherBtn.setAttribute( 'aria-expanded', 'false' );
				var otherAnswer = document.getElementById( otherBtn.getAttribute( 'aria-controls' ) );
				if ( otherAnswer ) otherAnswer.classList.remove( 'is-open' );
			} );

			// Toggle this item
			this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
			if ( isExpanded ) {
				answer.classList.remove( 'is-open' );
			} else {
				answer.classList.add( 'is-open' );
			}
		} );
	} );
} );
