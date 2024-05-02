import Vue from '../../vendor/vue-custom'

import TaxonomyPicker from './TaxonomyPicker.vue'
Vue.component('TaxonomyPicker', TaxonomyPicker)

document.addEventListener('DOMContentLoaded', function() {
  window.apps = []
  document.querySelectorAll('.taxonomy-picker-container').forEach(container => {
    window.apps.push(new Vue({
      el: container,
      data: {
        pickerDisplayed: null, // pickerDisplayed is used so only one picker is displayed at a time
        config: {}
      }
    }))
  })
})