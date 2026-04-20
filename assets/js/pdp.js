document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.myogenix-pdp__faq-question' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
			var answer   = document.getElementById( this.getAttribute( 'aria-controls' ) );

			this.setAttribute( 'aria-expanded', String( ! expanded ) );
			answer.hidden = expanded;
		} );
	} );
} );
