'use strict';

var Exchange = {
    revertNames: ['Perfect Money USD', 'Cryptocheck USD', 'Bitcoin', 'Btc-e USD', 'XMR'],
    btcSubNames: ['Btc-e USD', 'XMR'],
    nonCryptoPairs: ['Perfect Money USD', 'Cryptocheck USD'],
    lastInputsData: {},
    clickAmountPos: null,
    init: function($form){
        Exchange.exchangesObj.form = $form;
        Exchange.exchangesObj.exchangesdirectionsjson = JSON.parse(Exchange.exchangesObj.form.find('.exchange-data-form').attr('data-exchanges_directions_json'));

        $form.on('click', '.stwiz-ct-head .first a, .stwiz-ct-head .second a', function(e){
            e.preventDefault();
            var $this = $( this );
            var subClass = $this.parents('.col-6').hasClass('first') ? 'first' : $this.parents('.col-6').hasClass('second') ? 'second' : null;

            var checkUnActive = $this.hasClass('active');
            $this.parent().find('a').each(function(){
                $( this ).removeClass('active');
            });
            if(checkUnActive){
                Exchange.set('filter_'+ subClass, undefined);
            }else{
                $this.addClass('active');
                Exchange.set('filter_'+ subClass, $this);
            }

            Exchange.set(subClass, undefined);
        });
        $form.on('click', '.stwiz-selc .stwiz-selc-item', function(e){
            e.preventDefault();
            var $this = $( this );
            var subClass = $this.parents('.col-6').hasClass('first') ? 'first' : $this.parents('.col-6').hasClass('second') ? 'second' : null,
                oldInputsData = {
                    sellamount: $form.find('form input[name="sellamount"]').val(),
                    buyamount: $form.find('form input[name="buyamount"]').val()
                };
            $form.find('.customrowstofill input').each(function() {
                if($( this ).val() === '') return;
                Exchange.lastInputsData[$( this ).attr('name')] = $( this ).val();
            });

            if(typeof(Exchange.exchangesObj.first) !== 'undefined' && typeof(Exchange.exchangesObj.second) !== 'undefined'){
                if(subClass === 'first' && typeof(Exchange.exchangesObj.exchangesdirectionsjson[$this.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')]) === 'undefined'){
                    Exchange.set('second', undefined);
                }
                if(subClass === 'second' && typeof(Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ $this.attr('data-type_id')]) === 'undefined'){
                    Exchange.set('first', undefined);
                }
            }

            if($this.hasClass('stwiz-selc-item--active')) Exchange.set(subClass, undefined);
            else Exchange.set(subClass, $this);

            // подставляем ранее сохраненные значения
            if(subClass === 'first' && oldInputsData.buyamount !== '') $form.find('form input[name="buyamount"]').val(oldInputsData.buyamount).keyup();
            if(subClass === 'second' && oldInputsData.sellamount !== '') $form.find('form input[name="sellamount"]').val(oldInputsData.sellamount).keyup();

            $form.find('.customrowstofill input').each(function() {
                if(typeof(Exchange.lastInputsData[$( this ).attr('name')]) !== 'undefined') {
                    $( this ).val(Exchange.lastInputsData[$( this ).attr('name')]);
                }
            });
        });

        // посчитаем перевод
        $form.on('keyup', 'form input[name="buyamount"], form input[name="sellamount"]', function(e){
            var curVal = $( this ).val().replace(/[^\d\.]/g, '');
            $( this ).val(curVal);

            $( this ).val($( this ).val().replace(',', '.'));
            if(typeof(Exchange.exchangesObj.first) === 'undefined' || typeof(Exchange.exchangesObj.second) === 'undefined') return false;
            var $this = $( this ),
                curDirectionPair = Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')],
                sellamount = $form.find('form input[name="sellamount"]'),
                buyamount = $form.find('form input[name="buyamount"]'),
                firstTypeId = Exchange.exchangesObj.first.attr('data-type_id'),
                secondTypeId = Exchange.exchangesObj.second.attr('data-type_id'),
                courseConverted = 0,
                comission_in = 0,
                comission_out = 0;

            Exchange.clickAmountPos = $this.attr('name');

            // считаем комиссию по sellamount
            if (secondTypeId === '16' || (secondTypeId === '26' && firstTypeId !== '16')) {
                courseConverted = curDirectionPair.real_buy;
                comission_out = /%/.test(curDirectionPair.pay_comission) ? curDirectionPair.pay_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.pay_comission;
                comission_in = /%/.test(curDirectionPair.buy_comission) ? curDirectionPair.buy_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.buy_comission;
                comission_in = comission_in * courseConverted;
            } else if (firstTypeId === '16' || (firstTypeId === '26' && secondTypeId !== '16')) {
                courseConverted = curDirectionPair.real_sell;
                comission_out = /%/.test(curDirectionPair.pay_comission) ? curDirectionPair.pay_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.pay_comission;
                comission_in = /%/.test(curDirectionPair.buy_comission) ? curDirectionPair.buy_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.buy_comission;
                comission_out = comission_out * courseConverted;
            } else {
                if (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1) courseConverted = curDirectionPair.real_sell;
                if (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1) courseConverted = curDirectionPair.real_buy;
            }

            if(Exchange.clickAmountPos === 'sellamount'){
                var amount = parseFloat(courseConverted) - parseFloat(comission_out) - parseFloat(comission_in);
                if (
                    (secondTypeId === '16' || (secondTypeId === '26' && firstTypeId !== '16')) ||
                    (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1 && Exchange.exchangesObj.first.attr('data-type') !== 'BTC')
                ) {
                    amount = amount / courseConverted * sellamount.val() / courseConverted;
                    amount = amount.toFixed(8);
                } else {
                    amount = amount * sellamount.val()
                    amount = amount.toFixed(3);
                }
                buyamount.val(amount);
            }

            if(Exchange.clickAmountPos === 'buyamount'){
                var amount = parseFloat(courseConverted) + parseFloat(comission_out) + parseFloat(comission_in);
                if (
                    (firstTypeId === '16' || (firstTypeId === '26' && secondTypeId !== '16')) ||
                    (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1 && Exchange.exchangesObj.second.attr('data-type') !== 'BTC')
                ) {
                    amount = amount / courseConverted * buyamount.val() / courseConverted;
                    amount = amount.toFixed(8);
                } else {
                    amount = amount * buyamount.val()
                    amount = amount.toFixed(3);
                }
                sellamount.val(amount);
            }


            $form.find('input[name="last_sell"]').val(curDirectionPair.real_sell);
            $form.find('input[name="last_buy"]').val(curDirectionPair.real_buy);

            if (Exchange.exchangesObj.first.attr('data-type') === 'BTC' && Exchange.exchangesObj.second.attr('data-name') === 'XMR') {
                $form.find('input[name="last_sell"]').val(curDirectionPair.real_buy);
                $form.find('input[name="last_buy"]').val(curDirectionPair.real_sell);
            }

            /*$form.find('.stwiz-bar-total .amount').text(buyamount.val());*/
        });

        $form.find('input[type="submit"]').on('click', function(e){
            e.preventDefault();

            if(typeof(Exchange.exchangesObj.first) === 'undefined' || typeof(Exchange.exchangesObj.second) === 'undefined') {
                Exchange.exchangesObj.form.find('.flash-notice').text('Необходимо выбрать валюту обмена').show();
                return false;
            }

            var checkSubmit = true;

            Exchange.exchangesObj.form.find('.form-drgroup').each(function(){
                var input = $( this ).find('input[name]');

                if (['20','21','22'].indexOf(Exchange.exchangesObj.second.attr('data-type_id')) !== -1 &&
                    input.attr('name') === 'cardnumber' &&
                    input.val() === ''
                ) {
                    input.val('0000 0000 0000 0000');
                }

                var isValidated = Exchange.validate(input);
                if(isValidated){
                    $( this ).removeClass('error');
                }else{
                    $( this ).addClass('error');
                    checkSubmit = false;
                }
            });

            Exchange.exchangesObj.form.find('.flash-notice').text('').hide();

            var checkSubmitAmounts = Exchange.validateAmounts([Exchange.exchangesObj.form.find('input[name="sellamount"]'), Exchange.exchangesObj.form.find('input[name="buyamount"]')]);

            if(!checkSubmit || !checkSubmitAmounts) return false;

            if ($('#optionsCheck1').parents('.checkbox-checked:eq(0)').hasClass('checkbox-checked')) {
                Exchange.exchangesObj.form.find('.flash-notice').text('необходимо согласится с правилами обмена').show();
                return false;
            }

            var dataObj = { rules: $('#optionsCheck1').parents('.checkbox--checked:eq(0)').hasClass('checkbox--checked') };
            Exchange.exchangesObj.form.find('input[type="text"]').each(function(){
                var $curinput = $( this );
                dataObj[$curinput.attr('name')] = $curinput.val();
            });

            Exchange.exchangesObj.form.find('input[type="hidden"]').each(function(){
                var $curinput = $( this );
                dataObj[$curinput.attr('name')] = $curinput.val();
            });

            $.ajax({
                url: 'lkexchange',
                type: 'POST',
                dataType: 'json',
                data: dataObj
            }).done(function(data){
                if(data.msg === 'course_error'){
                    Exchange.exchangesObj.form.find('.flash-notice').text(data.notice).show();
                    Exchange.exchangesObj.form.find('input[name="buyamount"]').val(data.data.buyamount);
                    /*Exchange.exchangesObj.form.find('input[name="last_sell"]').val(data.data.last_sell);
                    Exchange.exchangesObj.form.find('input[name="last_buy"]').val(data.data.last_buy);*/
                    Exchange.exchangesObj.form.find('.amount').text(data.data.buyamount);

                    Exchange.exchangesObj.exchangesdirectionsjson[data.data.pay_id +'_'+ data.data.buy_id]['buy'] = data.data.last_buy;
                    Exchange.exchangesObj.exchangesdirectionsjson[data.data.pay_id +'_'+ data.data.buy_id]['sell'] = data.data.last_sell;
                }else if(data.msg === 'multiplicity_error'){
                    Exchange.exchangesObj.form.find('.flash-notice').text(data.notice).show();
                    Exchange.exchangesObj.form.find('input[name="sellamount"]').val(data.data.sellamount);
                    Exchange.exchangesObj.form.find('input[name="buyamount"]').val(data.data.buyamount);
                    Exchange.exchangesObj.form.find('.amount').text(data.data.buyamount);

                    Exchange.exchangesObj.exchangesdirectionsjson[data.data.pay_id +'_'+ data.data.buy_id]['buy'] = data.data.last_buy;
                    Exchange.exchangesObj.exchangesdirectionsjson[data.data.pay_id +'_'+ data.data.buy_id]['sell'] = data.data.last_sell;
                }else if(data.msg === 'error_time'){
                    Exchange.exchangesObj.form.find('.flash-notice').text(data.notice).show();
                }else if(data.msg === 'error'){
                    for (var i in data.data) {
                        Exchange.exchangesObj.form.find('input[name="' + data.data[i]['name'] + '"]').parents('.form-drgroup').addClass('error');
                        if (data.data[i]['name'] === 'phonenumber') Exchange.exchangesObj.form.find('.flash-notice').text(data.data[i]['msg']).show();
                    }
                }else if(data.msg === 'sber_validate'){
                    $('#modal-sberval').addClass('in').show();
                    $('body').append('<div class="modal-backdrop fade in"></div>');
                }else if(data.msg === 'success'){
                    document.location.href = 'lkexchange?hashlink='+ data.hashlink;
                }
            });
        });

        $form.on('click', '.pair-turn', function () {

            if(typeof(Exchange.exchangesObj.first) === 'undefined' || typeof(Exchange.exchangesObj.second) === 'undefined') return false;

            var first = Exchange.exchangesObj.first.attr('data-type_id');
            var second = Exchange.exchangesObj.second.attr('data-type_id');

            if (typeof(Exchange.exchangesObj.exchangesdirectionsjson[second + '_' + first]) !== 'undefined') {
                $form.find('.stwiz-ct-head .first a.active').trigger('click');
                $form.find('.stwiz-ct-head .second a.active').trigger('click');
                $form.find('.stwiz-selc .stwiz-selc-item--active').trigger('click');

                $form.find('.stwiz-selc .first .stwiz-selc-item[data-type_id="'+ second +'"]').trigger('click');
                $form.find('.stwiz-selc .second .stwiz-selc-item[data-type_id="'+ first +'"]').trigger('click');
            } else {
                Exchange.exchangesObj.form.find('.flash-notice').text('Данный обмен инвертировать невозможно.').show();
            }

            /*Exchange.set('filter_first', undefined);
            Exchange.set('filter_second', undefined);

            Exchange.set('first', undefined);
            Exchange.set('second', undefined);*/
        });

        Exchange.coursesRefreshTimer = setInterval(function() {
            $.ajax({
                url: 'getmaindata',
                type: 'POST',
                dataType: 'json',
                data: { _csrf_token: Exchange.exchangesObj.form.find('input[name="_csrf_token"]').val() }
            }).done(function(data) {
                Exchange.exchangesObj.exchangesdirectionsjson = data.exchanges_directions_json;

                var firstundefined = typeof(Exchange.exchangesObj.first) === 'undefined';
                var secondundefined = typeof(Exchange.exchangesObj.second) === 'undefined';

                if (!firstundefined && secondundefined) {
                    var first = Exchange.exchangesObj.first;
                    first.trigger('click');
                    first.trigger('click');
                };

                if (firstundefined && !secondundefined) {
                    var second = Exchange.exchangesObj.second;
                    second.trigger('click');
                    second.trigger('click');
                };

                if (!firstundefined && !secondundefined) {
                    var curDirectionPair = Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')];

                    var lastBuy = Exchange.exchangesObj.form.find('input[name="last_buy"]'),
                        lastSell = Exchange.exchangesObj.form.find('input[name="last_sell"]'),
                        revert = Exchange.exchangesObj.first.attr('data-name') === 'Bitcoin' && Exchange.btcSubNames.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1;

                    if(lastBuy.val() !== '') {
                        lastBuy.val(revert ? curDirectionPair.real_sell : curDirectionPair.real_buy);
                    }
                    if(lastSell.val() !== '') {
                        lastSell.val(revert ? curDirectionPair.real_buy : curDirectionPair.real_sell);
                    }

                    var dimension = 0;
                    if (Exchange.revertNames.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1) {
                        dimension = curDirectionPair.sell;
                    } else {
                        dimension = 1 / curDirectionPair.buy;
                    }

                    if (Exchange.exchangesObj.first.attr('data-name').trim() === 'XMR' && Exchange.exchangesObj.second.attr('data-type') === 'BTC') {
                        dimension = 1 / curDirectionPair.buy;
                    }

                    if (Exchange.exchangesObj.first.find('.stwiz-selc-item__value').text() === '1.000') {
                        Exchange.exchangesObj.second.find('.stwiz-selc-item__value').text(parseFloat(dimension).toFixed(3));
                    }

                    if (Exchange.exchangesObj.second.find('.stwiz-selc-item__value').text() === '1.000') {
                        Exchange.exchangesObj.first.find('.stwiz-selc-item__value').text(parseFloat(dimension).toFixed(3));
                    }

                    var sellamount = parseFloat($form.find('form input[name="sellamount"]').val()),
                        buyamount = parseFloat($form.find('form input[name="buyamount"]').val()),
                        firstTypeId = Exchange.exchangesObj.first.attr('data-type_id'),
                        secondTypeId = Exchange.exchangesObj.second.attr('data-type_id'),
                        courseConverted = 0,
                        comission_in = 0,
                        comission_out = 0;

                    // считаем комиссию по sellamount
                    if (secondTypeId === '16' || (secondTypeId === '26' && firstTypeId !== '16')) {
                        courseConverted = curDirectionPair.real_buy;
                        comission_out = /%/.test(curDirectionPair.pay_comission) ? curDirectionPair.pay_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.pay_comission;
                        comission_in = /%/.test(curDirectionPair.buy_comission) ? curDirectionPair.buy_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.buy_comission;
                        comission_in = comission_in * courseConverted;
                    } else if (firstTypeId === '16' || (firstTypeId === '26' && secondTypeId !== '16')) {
                        courseConverted = curDirectionPair.real_sell;
                        comission_out = /%/.test(curDirectionPair.pay_comission) ? curDirectionPair.pay_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.pay_comission;
                        comission_in = /%/.test(curDirectionPair.buy_comission) ? curDirectionPair.buy_comission.replace('%', '') * courseConverted / 100 : curDirectionPair.buy_comission;
                        comission_out = comission_out * courseConverted;
                    } else {
                        if (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1) courseConverted = curDirectionPair.real_sell;
                        if (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1) courseConverted = curDirectionPair.real_buy;
                    }

                    if(Exchange.clickAmountPos === 'sellamount'){
                        var amount = parseFloat(courseConverted) - parseFloat(comission_out) - parseFloat(comission_in);
                        if (
                            (secondTypeId === '16' || (secondTypeId === '26' && firstTypeId !== '16')) ||
                            (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1 && Exchange.exchangesObj.first.attr('data-type') !== 'BTC')
                        ){
                            amount = amount / courseConverted * sellamount / courseConverted;
                            amount = amount.toFixed(8);
                        } else {
                            amount = amount * sellamount;
                            amount = amount.toFixed(3);
                        }
                        $form.find('form input[name="buyamount"]').val(amount);
                    }

                    if(Exchange.clickAmountPos === 'buyamount'){
                        var amount = parseFloat(courseConverted) + parseFloat(comission_out) + parseFloat(comission_in);
                        if (
                            (firstTypeId === '16' || (firstTypeId === '26' && secondTypeId !== '16')) ||
                            (Exchange.nonCryptoPairs.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1 && Exchange.exchangesObj.second.attr('data-type') !== 'BTC')
                        ){
                            amount = amount / courseConverted * buyamount / courseConverted;
                            amount = amount.toFixed(8);
                        } else {
                            amount = amount * buyamount;
                            amount = amount.toFixed(3);
                        }
                        $form.find('form input[name="sellamount"]').val(amount);
                    }
                }
            });
        }, 10000);
    },
    exchangesObj: {},
    set: function(name, $value){
        Exchange.exchangesObj.form.find('.flash-notice').text('').hide();
        Exchange.exchangesObj.form.find('.form-drgroup.error').removeClass('error');
        Exchange.exchangesObj.email = Exchange.exchangesObj.form.find('.exchange-data-form').attr('data-email');
        Exchange.exchangesObj[name] = $value;

        var generalFilter = { pay_ids_passed: [], buy_ids_passed: [] };

        if(typeof(Exchange.exchangesObj.filter_first) !== 'undefined') generalFilter.pay_currency_id = Exchange.exchangesObj.filter_first.attr('data-currency_id');
        if(typeof(Exchange.exchangesObj.filter_second) !== 'undefined') generalFilter.buy_currency_id = Exchange.exchangesObj.filter_second.attr('data-currency_id');

        Exchange.exchangesObj.form.find('.stwiz-selc .'+ name +' .stwiz-selc-item').removeClass('stwiz-selc-item--active');
        if(typeof(Exchange.exchangesObj[name]) !== 'undefined')
            Exchange.exchangesObj[name].addClass('stwiz-selc-item--active');

        // усложним логику по сортировке
        if(typeof(Exchange.exchangesObj.first) !== 'undefined'){
            for(var i in Exchange.exchangesObj.exchangesdirectionsjson){
                var curExchangeDirection = Exchange.exchangesObj.exchangesdirectionsjson[i];
                if(curExchangeDirection.pay_id == Exchange.exchangesObj.first.attr('data-type_id'))
                    generalFilter.buy_ids_passed[(generalFilter.buy_ids_passed).length] = curExchangeDirection.buy_id;
            }
        }

        if(typeof(Exchange.exchangesObj.second) !== 'undefined'){
            for(var i in Exchange.exchangesObj.exchangesdirectionsjson){
                var curExchangeDirection = Exchange.exchangesObj.exchangesdirectionsjson[i];
                if(curExchangeDirection.buy_id == Exchange.exchangesObj.second.attr('data-type_id'))
                    generalFilter.pay_ids_passed[(generalFilter.pay_ids_passed).length] = curExchangeDirection.pay_id;
            }
        }

        var costs_for_first = [],
            costs_for_second = [],
            dimension = 1;



        var $itemsToSort = $('.stwiz-selc .second .stwiz-selc-item');
        $itemsToSort.each(function(){
            var $curThis = $( this );
            $curThis.find('.stwiz-selc-item__value').attr('corse-count', 1).text('');

            $curThis.find('.stwiz-selc-item__value').text('');
            $curThis.find('.stwiz-selc-item__currency').text('');

            if((generalFilter.buy_ids_passed).length > 0 && (generalFilter.buy_ids_passed).indexOf($curThis.attr('data-type_id')) === -1) {
                if(typeof(Exchange.exchangesObj.second) === 'undefined') $curThis.css({ 'display': 'none' });
                $curThis.attr('calc', false);
            }else if(typeof(generalFilter.buy_currency_id) !== 'undefined' && generalFilter.buy_currency_id !== $curThis.attr('data-currency_id')){
                if(typeof(Exchange.exchangesObj.second) === 'undefined') $curThis.css({ 'display': 'none' });
                $curThis.attr('calc', false);
            }else{
                $curThis.css({ 'display': 'table' });
                $curThis.attr('calc', true);
            }

            if($curThis.attr('calc') === 'true' && (generalFilter.buy_ids_passed).length > 0){
                var curJsonPair = Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ $curThis.attr('data-type_id')];
                costs_for_first[costs_for_first.length] = curJsonPair.buy;
            }
        });

        var $itemsToSort = $('.stwiz-selc .first .stwiz-selc-item');
        $itemsToSort.each(function(){
            var $curThis = $( this );
            $curThis.find('.stwiz-selc-item__value').attr('corse-count', 1).text('');

            if((generalFilter.pay_ids_passed).length > 0 && (generalFilter.pay_ids_passed).indexOf($curThis.attr('data-type_id')) === -1){
                if(typeof(Exchange.exchangesObj.first) === 'undefined') $curThis.css({ 'display': 'none' });
                $curThis.attr('calc', false);
            }else if(typeof(generalFilter.pay_currency_id) !== 'undefined' && generalFilter.pay_currency_id !== $curThis.attr('data-currency_id')){
                if(typeof(Exchange.exchangesObj.first) === 'undefined') $curThis.css({ 'display': 'none' });
                $curThis.attr('calc', false);
            }else{
                $curThis.css({ 'display': 'table' });
                $curThis.attr('calc', true);
            }

            if($curThis.attr('calc') === 'true' && (generalFilter.pay_ids_passed).length > 0){
                var curJsonPair = Exchange.exchangesObj.exchangesdirectionsjson[$curThis.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')];
                costs_for_second[costs_for_second.length] = curJsonPair.sell;
            }
        });

        // выбор по левому столбцу
        if(typeof(Exchange.exchangesObj.first) !== 'undefined' &&
                typeof(costs_for_first.sort()[0]) !== 'undefined'){

            if(Exchange.revertNames.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1){
                while((dimension * (costs_for_first.sort())[0]) < 1) dimension *= 10;
                var course = 'sell';
            }else{
                dimension = 1 / (costs_for_first.sort())[0];
                var course = 'buy';
            }

            Exchange.exchangesObj.first.find('.stwiz-selc-item__value').text((Exchange.exchangesObj.first.find('.stwiz-selc-item__value').attr('corse-count') * dimension).toFixed(3));
            var $itemsToSort = $('.stwiz-selc .second .stwiz-selc-item');
            $itemsToSort.each(function(){
                var $curThis = $( this );
                if($curThis.attr('calc') === 'true' && (generalFilter.buy_ids_passed).length > 0){

                    var curJsonPair = Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ $curThis.attr('data-type_id')];
                    var newValue = curJsonPair[course] * dimension;

                    $curThis.find('.stwiz-selc-item__value').text(newValue.toFixed(3));

                    if ($curThis.attr('data-name') === 'Bitcoin')
                        $curThis.find('.stwiz-selc-item__value').text(newValue.toFixed(8));

                    if ($curThis.attr('data-type') === 'BTC' && Exchange.exchangesObj.first.attr('data-name').trim() === 'XMR')
                        $curThis.find('.stwiz-selc-item__value').text((curJsonPair['buy'] * dimension).toFixed(3));

                    if (Exchange.btcSubNames.indexOf($curThis.attr('data-name').trim()) !== -1) {
                        $curThis.find('.stwiz-selc-item__currency').text($curThis.attr('data-name'));
                    } else {
                        $curThis.find('.stwiz-selc-item__currency').text($curThis.attr('data-type'));
                    }
                }
            });
        }

        // выбор по правому столбцу
        if(typeof(Exchange.exchangesObj.second) !== 'undefined' &&
           typeof(costs_for_second.sort()[costs_for_second.length - 1]) !== 'undefined' &&
           typeof(Exchange.exchangesObj.first) === 'undefined'
        ) {
            if(Exchange.revertNames.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1){
                var course = 'buy';
            }else{
                dimension = (costs_for_second.sort())[costs_for_second.length - 1];
                var course = 'sell';
            }

            Exchange.exchangesObj.second.find('.stwiz-selc-item__value').text((dimension / (Exchange.exchangesObj.second.find('.stwiz-selc-item__value').attr('corse-count'))).toFixed(3));
            var $itemsToSort = $('.stwiz-selc .first .stwiz-selc-item');
            $itemsToSort.each(function(){
                var $curThis = $( this );
                if($curThis.attr('calc') === 'true' && (generalFilter.pay_ids_passed).length > 0){

                    var curJsonPair = Exchange.exchangesObj.exchangesdirectionsjson[$curThis.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')];
                    var newValue = dimension / curJsonPair[course];

                    $curThis.find('.stwiz-selc-item__value').text(newValue.toFixed(3));

                    if ($curThis.attr('data-type') === 'BTC' && Exchange.exchangesObj.second.attr('data-name').trim() === 'XMR') {
                        $curThis.find('.stwiz-selc-item__value').text((dimension / curJsonPair['buy']).toFixed(3));
                    }

                    if (Exchange.btcSubNames.indexOf($curThis.attr('data-name').trim()) !== -1) {
                        $curThis.find('.stwiz-selc-item__currency').text($curThis.attr('data-name'));
                    } else {
                        $curThis.find('.stwiz-selc-item__currency').text($curThis.attr('data-type'));
                    }
                }
            });
        }

        // заполним поля нашей формы
        var $exchangeFom = Exchange.exchangesObj.form.find('form'),
            maskPlaceholder;

        if(typeof(Exchange.exchangesObj.first) !== 'undefined'){

            if (Exchange.btcSubNames.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1) {
                $exchangeFom.find('.stwiz-bar-head__revert .from').text(Exchange.exchangesObj.first.attr('data-name'));
            } else {
                $exchangeFom.find('.stwiz-bar-head__revert .from').text(Exchange.exchangesObj.first.attr('data-type'));
            }

            $exchangeFom.find('input[name="pay_currency_id"]').val(Exchange.exchangesObj.first.attr('data-type_id'));
            $exchangeFom.find('.givencurrency .text-primary').text(Exchange.exchangesObj.first.find('.stwiz-selc-item__label').text());
            $exchangeFom.find('.givencurrency .input-group-addon span').text(Exchange.exchangesObj.first.attr('data-type'));

            maskPlaceholder = Exchange.exchangesObj.first.attr('data-type');
            if(Exchange.exchangesObj.first.attr('data-type') === 'BTC' && Exchange.btcSubNames.indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1)
                maskPlaceholder = Exchange.exchangesObj.first.attr('data-name').trim();

            $exchangeFom.find('.givencurrency .input-group-addon span').text(maskPlaceholder);
            $exchangeFom.find('.givencurrency input[name="sellamount"]').attr('placeholder', 'От '+ arrayOfPlaceholdersAmount[maskPlaceholder][0] +' до '+ arrayOfPlaceholdersAmount[maskPlaceholder][1]).val('');
        }else{
            $exchangeFom.find('.stwiz-bar-head__revert .from').text('-');
            $exchangeFom.find('input[name="pay_currency_id"]').val('');
            $exchangeFom.find('.givencurrency .text-primary').text('-');
            $exchangeFom.find('.givencurrency .input-group-addon span').text('-');
            $exchangeFom.find('.givencurrency input[name="sellamount"]').attr('placeholder', '-').val('');
        }

        if(typeof(Exchange.exchangesObj.second) !== 'undefined'){

            if (Exchange.btcSubNames.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1) {
                $exchangeFom.find('.stwiz-bar-head__revert .to').text(Exchange.exchangesObj.second.attr('data-name'));
            } else {
                $exchangeFom.find('.stwiz-bar-head__revert .to').text(Exchange.exchangesObj.second.attr('data-type'));
            }

            $exchangeFom.find('input[name="buy_currency_id"]').val(Exchange.exchangesObj.second.attr('data-type_id'));
            $exchangeFom.find('.takencurrency .text-primary').text(Exchange.exchangesObj.second.find('.stwiz-selc-item__label').text());

            maskPlaceholder = Exchange.exchangesObj.second.attr('data-type');
            if(Exchange.exchangesObj.second.attr('data-type') === 'BTC' && Exchange.btcSubNames.indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1)
                maskPlaceholder = Exchange.exchangesObj.second.attr('data-name').trim();

            $exchangeFom.find('.takencurrency .input-group-addon span').text(maskPlaceholder);
            $exchangeFom.find('.takencurrency input[name="buyamount"]').attr('placeholder', 'От '+ arrayOfPlaceholdersAmount[maskPlaceholder][0] +' до '+ arrayOfPlaceholdersAmount[maskPlaceholder][1]).val('');
        }else{
            $exchangeFom.find('.stwiz-bar-head__revert .to').text('-');
            $exchangeFom.find('input[name="buy_currency_id"]').val('');
            $exchangeFom.find('.takencurrency .text-primary').text('-');
            $exchangeFom.find('.takencurrency .input-group-addon span').text('-');
            $exchangeFom.find('.takencurrency input[name="buyamount"]').attr('placeholder', '-').val('');
        }

        $exchangeFom.find('.customrowtofill').remove();
        $exchangeFom.find('.phone_notice').remove();

        if(typeof(Exchange.exchangesObj.first) !== 'undefined' && typeof(Exchange.exchangesObj.second) !== 'undefined'){
            var curDirectionPair = Exchange.exchangesObj.exchangesdirectionsjson[Exchange.exchangesObj.first.attr('data-type_id') +'_'+ Exchange.exchangesObj.second.attr('data-type_id')];

            var firstType = Exchange.exchangesObj.first.attr('data-type') === 'BTC' && Exchange.exchangesObj.first.attr('data-name').trim() === 'XMR' ? 'XMR' : Exchange.exchangesObj.first.attr('data-type'),
                secondType = Exchange.exchangesObj.second.attr('data-type') === 'BTC' && Exchange.exchangesObj.second.attr('data-name').trim() === 'XMR' ? 'XMR' : Exchange.exchangesObj.second.attr('data-type');

            var textComissionT = curDirectionPair.buy_comission !== '0' ? '(' + curDirectionPair.buy_comission_text + ' ' + curDirectionPair.buy_comission + ' ' + secondType + ')' : '';
            var textComissionG = curDirectionPair.pay_comission !== '0' ? '(' + curDirectionPair.pay_comission_text + ' ' + curDirectionPair.pay_comission + ' ' + firstType + ')' : '';

            $exchangeFom.find('.takencurrency .text-primary').html($exchangeFom.find('.takencurrency .text-primary').text() + '<div class="comission">' + textComissionT + '</div>');
            $exchangeFom.find('.givencurrency .text-primary').html($exchangeFom.find('.givencurrency .text-primary').text() + '<div class="comission">' + textComissionG + '</div>');

            // еще немного логики
            var priorityArray = ['BTC', 'EUR', 'USD', 'RUB'];
            var firstAmount = Exchange.exchangesObj.first.find('.stwiz-selc-item__value');
            var secondAmount = Exchange.exchangesObj.second.find('.stwiz-selc-item__value');

            $('.stwiz-selc .stwiz-selc-item').each(function() {
                if($( this ).hasClass('stwiz-selc-item--active')) return;
                $( this ).find('.stwiz-selc-item__value').html('');
            });

            if(
                (priorityArray.indexOf(Exchange.exchangesObj.first.attr('data-type')) < priorityArray.indexOf(Exchange.exchangesObj.second.attr('data-type'))) ||
                (Exchange.exchangesObj.first.attr('data-type') === 'BTC' && Exchange.exchangesObj.second.attr('data-name') === 'XMR')
            ) {
                secondAmount.html((parseFloat(secondAmount.text()) / parseFloat(firstAmount.text())).toFixed(3));
                firstAmount.html('1.000');
            } else {
                firstAmount.html((parseFloat(firstAmount.text()) / parseFloat(secondAmount.text())).toFixed(3));
                secondAmount.html('1.000');
            }

            var rowsArray = [];
            for(var i in curDirectionPair.rows_to_fill){
                var extra_to_fill = curDirectionPair.rows_to_fill[i];
                var value = (extra_to_fill.inputname === 'email' && typeof(Exchange.exchangesObj.email) !== 'undefined') ? Exchange.exchangesObj.email : '';

                var row = '<div class="form-drgroup customrowtofill">'+
                               '<div class="input-group">'+
                                   '<div class="input-group-addon">'+
                                       '<label>'+ extra_to_fill.name +'</label>'+
                                   '</div>'+
                                   '<input class="form-drgroup-control control-gplace" type="text" name="'+ extra_to_fill.inputname +'" placeholder="'+ extra_to_fill.placeholder +'" value="'+ value +'">'+
                               '</div>'+
                          '</div>';

                if (extra_to_fill.inputname === 'phonenumber' &&
                    Exchange.exchangesObj.first.attr('data-name') === 'Сбербанк' &&
                    Exchange.exchangesObj.second.attr('data-type') === 'BTC'
                ) {
                    row += '<div class="phone_notice">(к номеру телефона должна быть привязана карта с которой будет осуществляться перевод. Также к номеру не должны быть привязаны карты разных клиентов банка)</div>';
                }

                if (extra_to_fill.inputname === 'cardnumber' &&
                    Exchange.exchangesObj.first.attr('data-type') === 'BTC' &&
                    Exchange.exchangesObj.second.attr('data-name') === 'Тинькофф'
                ) {
                    row += '<div class="phone_notice">(переводы в тинькофф проходят только через салоны связи: евросеть, связной, итд.)</div>';
                }

                rowsArray[rowsArray.length] = row;
            }
            $exchangeFom.find('.customrowstofill').append(rowsArray.join(''));

            if (curDirectionPair.auto_disabled === '1') {
                $exchangeFom.find('input[type="submit"]').hide();
                $exchangeFom.find('.flash-notice').text('Обмен по данному направлению кратковременно приостановлен.').show();
            } else {
                $exchangeFom.find('input[type="submit"]').show();
                $exchangeFom.find('.flash-notice').hide();
            }
        }
    },
    validate: function($rowtovalidate) {

        if ($rowtovalidate.attr('name') === 'email' && !(/^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/.test($rowtovalidate.val()))) return false;
        if ($rowtovalidate.attr('name') === 'phonenumber' && !(/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/.test($rowtovalidate.val())) && $rowtovalidate.val() !== '') return false;
        if ($rowtovalidate.attr('name') === 'sellamount' && !(!isNaN(parseFloat($rowtovalidate.val())) && isFinite($rowtovalidate.val()))) return false;
        if ($rowtovalidate.attr('name') === 'buyamount' && !(!isNaN(parseFloat($rowtovalidate.val())) && isFinite($rowtovalidate.val()))) return false;
        if (
            (['Наличные RUR', 'Наличные USD', 'Наличные EURO'].indexOf(Exchange.exchangesObj.first.attr('data-name').trim()) !== -1 ||
             ['Наличные RUR', 'Наличные USD', 'Наличные EURO'].indexOf(Exchange.exchangesObj.second.attr('data-name').trim()) !== -1) &&
            ($rowtovalidate.attr('name') === 'fio' || $rowtovalidate.attr('name') === 'phonenumber') &&
            $rowtovalidate.val() === ''
        ) {
            Exchange.exchangesObj.form.find('.flash-notice').text('Для посещения офиса вам нужно указать ФИО (для заказа пропуска у охраны), а также контактный номер телефона.').show();
        }
        if ($rowtovalidate.val().trim() === '') return false;

        return true;
    },
    validateAmounts: function(inputsAmount){
        var passAmount = {};

        for (var name in inputsAmount) {
            if (inputsAmount[name].attr('name') === 'sellamount') var objNumber = 'first';
            if (inputsAmount[name].attr('name') === 'buyamount') var objNumber = 'second';

            var maskPlaceholder = Exchange.exchangesObj[objNumber].attr('data-type');
            if (Exchange.exchangesObj[objNumber].attr('data-type') === 'BTC' && Exchange.btcSubNames.indexOf(Exchange.exchangesObj[objNumber].attr('data-name').trim()) !== -1)
                maskPlaceholder = Exchange.exchangesObj[objNumber].attr('data-name').trim();

            if(inputsAmount[name].val() >= arrayOfPlaceholdersAmount[maskPlaceholder][0] && inputsAmount[name].val() <= arrayOfPlaceholdersAmount[maskPlaceholder][1]) {
                return true;
            } else if (inputsAmount[name].val() < arrayOfPlaceholdersAmount[maskPlaceholder][0]) {
                passAmount[inputsAmount[name].attr('name')] = {input: inputsAmount[name], msg: 'Выбранная сумма меньше допустимой.'};
            } else if (inputsAmount[name].val() > arrayOfPlaceholdersAmount[maskPlaceholder][1]) {
                passAmount[inputsAmount[name].attr('name')] = {input: inputsAmount[name], msg: 'Выбранная сумма больше допустимой.'};
            }
        };

        for (var name in passAmount) {
            Exchange.exchangesObj.form.find('.flash-notice').text(passAmount[name].msg).show();
            passAmount[name].input.parents('.form-drgroup').addClass('error');
        }

        return false;
    }
};

$(document).ready(function(){
    if($('.msecinfo').hasClass('msecinfo')) Exchange.init($('.msecinfo'));
});