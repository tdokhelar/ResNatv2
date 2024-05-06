import Vue from '../vendor/vue-custom'
import { geocodeAddress } from '../element-form/geocode-address'

document.addEventListener('DOMContentLoaded', function() {
    if ($('.element-data-fields').length > 0) {
        new Vue({
            el: ".element-data-fields",
            data: {
                newFields: [],
            },
            methods: {
                addField() {
                    this.newFields.push('')               
                }
            },
        })
    }

    $('.geocode-btn').on('click', function() {
        var address = "";
        if ($('#input-customFormatedAddress').val()) {
          address = $('#input-customFormatedAddress').val();
        } else {
          if ($('#input-streetNumber').val()) address += $('#input-streetNumber').val() + ' ';
          address += $('#input-streetAddress').val() + ', ';
          address += $('#input-postalCode').val() + ' ' + $('#input-addressLocality').val() + ', ';
          address += $('#input-addressCountry').val();
        }
        const countryCode = $('#input-addressCountry').val();
        geocodeAddress(address, countryCode);
    })
})