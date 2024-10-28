$(document).ready(function () {
    
    if( $('input[type="submit"]').length > 0 && $('input[type="submit"]').val() == "Update" ) {
        $('#order_payment_edit').delegate('#refundbutton', 'click', function (e) {
            var vamount = $('input[name=orders_payment_amount]').val();
            if( !vamount ) {
                alert('Please enter valid amount.');
                $('input[name=orders_payment_amount]').focus();
                return;
            }
            else if( !$.isNumeric(vamount) ) {
                alert('Please enter valid amount.');
                $('input[name=orders_payment_amount]').focus();
                $('input[name=orders_payment_amount]').select();
                return;
            }
            $(this).prop('disabled',true);
            $.fn.returnPayment();
        });

        $.fn.timerCount = function() {
            var timer2 = "1:30";
            var interval = setInterval(function() {
                var timer = timer2.split(':');
                var minutes = parseInt(timer[0], 10);
                var seconds = parseInt(timer[1], 10);
                --seconds;
                minutes = (seconds < 0) ? --minutes : minutes;
                seconds = (seconds < 0) ? 59 : seconds;
                seconds = (seconds < 10) ? '0' + seconds : seconds;
                $('#timebox').html('0'+minutes + ':' + seconds);
                if (minutes < 0) clearInterval(interval);
                if ((seconds <= 0) && (minutes <= 0)) {
                    clearInterval(interval);
                    $('#timerbox').hide();
                    $("#resendcode").prop('disabled',false);
                    $("#resendcode").removeClass('disabledresendcode');
                }
                timer2 = minutes + ':' + seconds;
            }, 1000);
        }

        $.fn.returnPayment = function() {
            $.post(payneteasy.return_url, { platform_id: payneteasy.platform_id, opyID: payneteasy.opyID, amount: $('input[name=orders_payment_amount]').val() }, function( data ) {
                data = JSON.parse(data);
                console.log(data);
                if(!data) {
                if( data.message ) $("#resp").html(data.message);
                } else {
                    $('input[name=payneteasy_uuid]').val(data.uuid);
                }
            });
        }


        $('#order_payment_edit').delegate('#resendcode', 'click', function (e) {
            e.preventDefault();
            $.each($('.input'), function (index, value) {
                $(this).val('');
            });
            $("#resp").html('');
        });

        $('#order_payment_edit').delegate('#donebutton', 'click', function (e) {
            location.reload();
        });

        $.fn.balancepayment = function() {
            $.post(payneteasy.balancepayment_url, { opyID: payneteasy.opyID }, function( data ) {
                data = JSON.parse(data);
                if( !data.error && Number(data.amount) <= 0 ) {
                    $('#order_payment_edit input[type="submit"]').parent().append('<label class="btn btn-danger" style="float:left;">'+data.message+'</label>');
                    $('#order_payment_edit input[type="submit"]').hide();
                    $('textarea[name="orders_payment_transaction_commentary"]').val('');
                }
                else {
                    $('input[name=orders_payment_amount]').val(data.amount);
                    $('#order_payment_edit input[type="submit"]').parent().append('<input type="button" class="btn btn-primary" id="refundbutton" value="Refund via PaynetEasy" style="float:left;">');
                    $('textarea[name="orders_payment_transaction_commentary"]').val('');
                    $('#order_payment_edit input[type="submit"]').show();
                }
            });
        }

        $.fn.checkAllBox = function() {
            var fullstring = ''; 
            $.each($('.input'), function (index, value) {
                fullstring += $(this).val();
            });
        }

        $('#order_payment_edit').delegate('.input', 'input', function (e) {
            $(this).val(
                $(this)
                    .val()
                    .replace(/[^0-9]/g, "").substr(0,1)
            );
            $.fn.checkAllBox();
        });
        
        $('#order_payment_edit').delegate('.input', 'keyup', function (e) {
            let key = e.keyCode || e.charCode;
            if (key == 8 || key == 46 || key == 37 || key == 40) {
                // Backspace or Delete or Left Arrow or Down Arrow
                $(this).prev().focus();
            } else if (key == 38 || key == 39 || $(this).val() != "") {
                // Right Arrow or Top Arrow or Value not empty
                $(this).next().focus();
            }
        });

        $('#order_payment_edit').delegate('.input', 'paste', function (e) {
            var obj = $('.input');
            var paste_data = e.originalEvent.clipboardData.getData("text");
            var paste_data_splitted = paste_data.split("");
            $.each(paste_data_splitted, function (index, value) {
                obj.eq(index).val(value);
            });
        });

        setTimeout(function(){
            $.fn.balancepayment();
        }, 500);

    }

});