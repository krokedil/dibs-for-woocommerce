(function ($) {
    'use strict';
	
	$(document).ready( function() {
        if ($('.product-type-variable')[0] && $('.variation_id').val() === '0'){
            // Product is a variable product and variable is not set
            $('.mp-add-to-cart').addClass( 'disabled');
        }
    });
    
    // Check for changes to .variation-id to disable/enable Masterpass button on single product page
    $(document).on('change', "input[name='variation_id']", function(){
        if( $('.variation_id').val() !== '' ) {
            $('.mp-add-to-cart').removeClass( 'disabled');
        } else {
            $('.mp-add-to-cart').addClass( 'disabled');
        }
    });
	
}(jQuery));
