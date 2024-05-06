export default {
  computed: {
    nodeClass() {
      return [`child-display-${this.childrenDisplay}`, {
        expanded: this.expanded,
        checked: this.item.checked, // only for options
        invalid: this.invalid,
        mandatory: this.item.isMandatory, // only for categories
        'with-name': this.displayName, // only for categories
        'single-option': this.item.singleOption, // only for categories
        'as-btn': this.displayAsButton // only for categories
      }]
    },
  },
  methods: {
    toggleExpand(value = !this.expanded) {
      if (!this.expandable) return
      if (value && !this.expanded) this.$emit('expand', this.item)
      if (!value && this.expanded) this.$emit('collapse', this.item)
    }
  }
}