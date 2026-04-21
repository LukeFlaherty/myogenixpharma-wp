document.addEventListener( 'DOMContentLoaded', function () {
	function initAccordion( selector ) {
		var questions = Array.prototype.slice.call(
			document.querySelectorAll( selector )
		);

		questions.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
				var answer     = document.getElementById( this.getAttribute( 'aria-controls' ) );

				questions.forEach( function ( otherBtn ) {
					if ( otherBtn === btn ) return;
					otherBtn.setAttribute( 'aria-expanded', 'false' );
					var otherAnswer = document.getElementById( otherBtn.getAttribute( 'aria-controls' ) );
					if ( otherAnswer ) otherAnswer.classList.remove( 'is-open' );
				} );

				this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				if ( isExpanded ) {
					answer.classList.remove( 'is-open' );
				} else {
					answer.classList.add( 'is-open' );
				}
			} );
		} );
	}

	initAccordion( '.myogenix-pdp__faq-question' );
	initAccordion( '.myogenix-pdp__cq-question' );
} );
