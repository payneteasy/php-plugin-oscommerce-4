$(document).ready(function () {
    
    $('form[name="one_page_checkout"]').submit(function (e) {
        hideErrorBoxes();

        var ccErrorFlag = true;
        var cc = new PaynetEasyCC();
    
        if (!cc.isValid($("#payneteasy-card-number").val())) {
            $("#payneteasy-card-number-error").fadeIn('slow');
            ccErrorFlag = false;
        }
        
        if (!cc.isExpirationDateValid($("#payneteasy-card-expiry-month").val(), $("#payneteasy-card-expiry-year").val())) {
            $("#payneteasy-card-expiry-error").fadeIn('slow');
            ccErrorFlag = false;
        }

        if ($("#payneteasy-card-name").val().length <= 0) {
            $("#payneteasy-card-name-error").fadeIn('slow');
            ccErrorFlag = false;
        }
        
        if (!cc.isSecurityCodeValid($("#payneteasy-card-number").val(), $("#payneteasy-card-cvv").val())) {
            $("#payneteasy-card-cvv-error").fadeIn('slow');
            ccErrorFlag = false;
        }

        if ($("#payneteasy-card-address").length > 0 && $("#payneteasy-card-address").val().length <= 0) {
            $("#payneteasy-card-address-error").fadeIn('slow');
            ccErrorFlag = false;
        }

        if ($("#payneteasy-card-zip").length > 0 && $("#payneteasy-card-zip").val().length <= 0) {
            $("#payneteasy-card-zip-error").fadeIn('slow');
            ccErrorFlag = false;
        }

        if (!ccErrorFlag) {
            $('.w-checkout-continue-btn button[type=submit]').prop('disabled', false);
            $('.w-checkout-continue-btn button[type=submit]').addClass('disabled-area');
            if( $('.hide-page').length > 0 ) $('.hide-page').remove();
            if( $('.fake-input').length > 0 ) $('.fake-input').remove();
            return ccErrorFlag;
        }
        return true;
    });

    window.PaynetEasyCreateCCForm = function() 
    {
        if ( $('input[name=payment][value="payneteasy"]').length <= 0 ) return;

        if ($('input[name=payment][value="payneteasy"]').is(':checked')){
            console.log(payneteasy_payment_method);
            if (payneteasy_payment_method == 'Direct') {

                if( $('#paynetCcBox').length > 0 ) $('#paynetCcBox').remove();
                var payneteasy_avs_string = '';
                var payneteasy_new_card = '';

                // if( payneteasy_avs_options == 'zipandaddress' ) payneteasy_avs_string = '<div class="row"><div class="col-75"><label for="payneteasy-card-address">Address <span class="required">*</span></label><input type="text" id="payneteasy-card-address" name="payneteasy-card-address" value="" maxlength="25"><div id="payneteasy-card-address-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter address for verification.</div></div></div><div class="col-25"><label for="payneteasy-card-zip">Zip <span class="required">*</span></label><input type="text" id="payneteasy-card-zip" name="payneteasy-card-zip" value="" maxlength="6"><div id="payneteasy-card-zip-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter zip for verification.</div></div></div></div>';
                // else if( payneteasy_avs_options == 'address' ) payneteasy_avs_string = '<div class="row"><div class="col-75"><label for="payneteasy-card-address">Address <span class="required">*</span></label><input type="text" id="payneteasy-card-address" name="payneteasy-card-address" value="" maxlength="25"><div id="payneteasy-card-address-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter address for verification.</div></div></div></div>';
                // else if( payneteasy_avs_options == 'zip' ) payneteasy_avs_string = '<div class="row"><div class="col-75"><label for="payneteasy-card-zip">Zip <span class="required">*</span></label><input type="text" id="payneteasy-card-zip" name="payneteasy-card-zip" value="" maxlength="6"><div id="payneteasy-card-zip-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter zip for verification.</div></div></div></div>';
                
                var formString = '<div class="container"><div class="row"><div class="logobox">'+payneteasy_logos+'</div></div>'+payneteasy_new_card+'<div id="new-card-panel"><div class="row"><div class="col-75"><label for="payneteasy-card-number">Credit Card Number <span class="required">*</span></label><input type="text" id="payneteasy-card-number" name="payneteasy-card-number" autocomplete="off" maxlength="23"><div id="payneteasy-card-number-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter a valid credit card number.</div></div></div><div class="col-25"><label for="payneteasy-card-expiry-month">Expiration <span class="required">*</span></label><div class="row"><div class="col-50-1"><select id="payneteasy-card-expiry-month" name="payneteasy-card-expiry-month"></select></div><div class="col-50-2"><select id="payneteasy-card-expiry-year" name="payneteasy-card-expiry-year"></select></div></div> <div id="payneteasy-card-expiry-error" class="required-message-wrap top-error-mes"><div class="required-message">Please select valid expiration date.</div></div> </div></div><div class="row"><div class="col-75"><label for="payneteasy-card-name">Name on Card <span class="required">*</span></label><input type="text" id="payneteasy-card-name" name="payneteasy-card-name" autocomplete="off" maxlength="25"><div id="payneteasy-card-name-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter name on credit card.</div></div></div><div class="col-25"><label for="payneteasy-card-cvv">CVV <span class="required">*</span></label><input type="password" maxlength="4" id="payneteasy-card-cvv" name="payneteasy-card-cvv" autocomplete="off"><div id="payneteasy-card-cvv-error" class="required-message-wrap top-error-mes"><div class="required-message" style="">Please enter a valid CVV.</div></div></div></div></div></div>';
                $("<div/>").attr('id','paynetCcBox').html(formString).appendTo($(".payment_class_payneteasy").parents('.type-1')[1]);

                for ( var cc_month_counter in payneteasy_cc_months ) {
                    var cc_month_value = payneteasy_cc_months[cc_month_counter][0];
                    var cc_month_text = $("<div/>").html(payneteasy_cc_months[cc_month_counter][1]).text();
                    $('<option/>').val(cc_month_value).text(cc_month_text).appendTo($('#payneteasy-card-expiry-month'));
                };
            
                for ( var cc_year_counter in payneteasy_cc_years ) {
                    var cc_year_value = payneteasy_cc_years[cc_year_counter][0];
                    var cc_year_text = payneteasy_cc_years[cc_year_counter][1];
                    $('<option/>').val(cc_year_value).text(cc_year_text).appendTo($('#payneteasy-card-expiry-year'));
                };
    
                window.PaynetEasyAddCardDetection();
                hideErrorBoxes();
    
                $(".payneteasy-card-head").click(function() {
                    var ind = parseInt( $(this).attr("rel") );
                    $(".payneteasy-payment-card").eq(ind-1).prop('checked', true);
                    $(".payneteasy-payment-card").change();
                });
                
                $(".payneteasy-payment-card").change(function() {
                    if( $(this).val() == 1 && $(this).is(":checked") ) {
                        $("#new-card-panel").slideUp();
                    }
                    else if( $(this).val() == 2 && $(this).is(":checked") ) {
                        $("#new-card-panel").slideDown();
                    }
                });
                 
                setTimeout(function() {
                    var address  = ($('#shipping_address-street_address').length>0?$('#shipping_address-street_address').val():payneteasy_street_address);
                    var postcode = ($('#shipping_address-postcode').length>0?$('#shipping_address-postcode').val():payneteasy_postcode);
                    $("#payneteasy-card-address").val(address);
                    $("#payneteasy-card-zip").val(postcode);
                },1000);
            }
        } else {
            $('#paynetCcBox').remove();
        }
    
    }
    
    window.PaynetEasyAddCardDetection = function()
    {
        $('#payneteasy-card-number').on('keydown',function(e){
            var deleteKeyCode = 8;
            var tabKeyCode = 9;
            var backspaceKeyCode = 46;
            if ((e.key>=0 && e.key<=9) ||
                 e.which === deleteKeyCode || // for delete key,
                    e.which === tabKeyCode || // for tab key   
                        e.which === backspaceKeyCode) // for backspace
            {
                return true;
            }
            else
            {
                return false;
            }
        });
        
        $('#payneteasy-card-number').keyup(function() {
            checkErrors();
            var val = $(this).val();
            var newval = '';
            var cardNumber = val.replace(/\s/g, '');
            for(var i=0; i < cardNumber.length; i++) {
                if(i%4 == 0 && i > 0) newval = newval.concat(' ');
                newval = newval.concat(cardNumber[i]);
            }
            $(this).val(newval);
            var detector = new PaynetEasyBrandDetection();
            var brand = detector.detect(cardNumber);
            $('.payneteasy-cc-logo').css('opacity','0.3');
            if (brand && brand != "unknown") {
                if($('#payneteasy-cc-'+brand).length <= 0 ) {
                    alert(brand.toUpperCase()+" credit card is not accepted");
                    $('#payneteasy-card-number').val("");
                }
                else {
                    $('#payneteasy-cc-'+brand).css('opacity','1');
                }
            }
        });

        $('#payneteasy-card-expiry-month').change(function (e) {
            checkErrors();
        });

        $('#payneteasy-card-expiry-year').change(function (e) {
            checkErrors();
        });

        $('#payneteasy-card-name').keyup(function (e) {
            checkErrors();
        });
        
        $("#payneteasy-card-name").on("keydown", function(event){
            // Allow controls such as backspace, tab etc.
            var arr = [8,9,16,17,20,32,35,36,37,38,39,40,45,46];
            // Allow letters
            for(var i = 65; i <= 90; i++){
              arr.push(i);
            }
            // Prevent default if not in array
            if(jQuery.inArray(event.which, arr) === -1){
                return false;
            }
            else return true;
        });

        $('#payneteasy-card-cvv').keyup(function (e) {
            checkErrors();
        });

        $('#payneteasy-card-cvv').on('keydown',function(e){
            var deleteKeyCode = 8;
            var tabKeyCode = 9;
            var backspaceKeyCode = 46;
            if ((e.key>=0 && e.key<=9) ||
                 (e.which>=96 && e.which<=105)  || // for num pad numeric keys
                 e.which === deleteKeyCode || // for delete key,
                    e.which === tabKeyCode || // for tab key
                        e.which === backspaceKeyCode) // for backspace
            {
                return true;
            }
            else
            {
                return false;
            }
        });

        if( $('#payneteasy-card-address').length > 0 ) {
            $('#payneteasy-card-address').keyup(function (e) {
                checkErrors();
            });
        }

        if( $('#payneteasy-card-zip').length > 0 ) {
            $('#payneteasy-card-zip').keyup(function (e) {
                checkErrors();
            });

            $('#payneteasy-card-zip').on('keydown',function(e){
                var deleteKeyCode = 8;
                var tabKeyCode = 9;
                var backspaceKeyCode = 46;
                if ((e.key>=0 && e.key<=9) ||
                     (e.which>=96 && e.which<=105)  || // for num pad numeric keys
                     e.which === deleteKeyCode || // for delete key,
                        e.which === tabKeyCode || // for tab key
                            e.which === backspaceKeyCode) // for backspace
                {
                    return true;
                }
                else
                {
                    return false;
                }
            });
        }

    }

    function checkErrors()
    {
        var allentered = true;
        if( $("#payneteasy-card-number").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-expiry-month").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-expiry-year").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-name").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-cvv").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-address").length > 0 && $("#payneteasy-card-address").val().length <= 0 ) allentered = false;
        if( $("#payneteasy-card-zip").length > 0 && $("#payneteasy-card-zip").val().length <= 0 ) allentered = false;    
        if( allentered ) {
            $('.w-checkout-continue-btn button[type=submit]').prop('disabled', false);
            $('.w-checkout-continue-btn button[type=submit]').removeClass('disabled-area');
        }
    }

    function hideErrorBoxes()
    {
        $("#payneteasy-card-cvv-error").css('display', 'none');
        $("#payneteasy-card-number-error").css('display', 'none');
        $("#payneteasy-card-expiry-error").css('display', 'none');
        $("#payneteasy-card-name-error").css('display', 'none');
        if( $("#payneteasy-card-address-error").length > 0 ) $("#payneteasy-card-address-error").css('display', 'none');
        if( $("#payneteasy-card-zip-error").length > 0 ) $("#payneteasy-card-zip-error").css('display', 'none');
    }

    if (typeof tl == 'function'){
        tl(function(){
            window.PaynetEasyCreateCCForm();
            try {
                checkout_payment_changed.set('window.PaynetEasyCreateCCForm');
            } catch (e ) {console.log(e ); }
        })
    }

});