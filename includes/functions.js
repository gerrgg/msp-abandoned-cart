jQuery( function( $ ){
    var $checkout = {
        $email_field: $('form.checkout input#billing_email'),

        init: function(){
            this.$email_field.on( 'blur', this.save_checkout_email );
        }, 

        save_checkout_email: function( e ){
            let email = e.target.value;
            if( email !== '' ){
                $.post( wp_ajax.url, { action: 'msp_save_checkout_email', email: email });
            }
        }
    }

    $checkout.init();
});