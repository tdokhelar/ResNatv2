export default {
  computed: {
    modelName() {
      return this.$parent.modelName
    },
    model() {
      return this.$parent.model
    },
    labelValue() {
      return this.translate()
    },
    helpValue() {
      return this.translate('_help')
    },
    hintValue() {
      return this.translate('_hint')
    },
    placeholderValue() {
      return this.translate('_placeholder')
    }
  },
  methods: {
    translationKey(suffix) {
      return ''
    },
    translate(suffix = '') {
      const key = this.translationKey(suffix)
      const result = this.t(key)
      return key == result ? null : result
    }
  }
}