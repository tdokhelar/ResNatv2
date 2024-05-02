import Vue from '../vendor/vue-custom'
import MarkerShapeInput from './components/MarkerShapeInput.vue'

document.addEventListener('DOMContentLoaded', function() {
    if ($('.gogo-marker-shape-picker').length > 0) {
        new Vue({
            el: ".gogo-marker-shape-picker",
            components: { MarkerShapeInput },
            data: {
                shape: ""
            },
            mounted() {
                this.shape = this.$el.dataset.value
            }
        })
    }
})
