export default {
  methods: {
    checkedOptionsFor(category) {
      return (category.options || [])
        .filter(option => option.displayInForm && option.checked)
        .sort((a,b) => a.checkedIndex < b.checkedIndex ? -1 : 1)
    },
    checkedCategoriesFor(option) {
      return (option.subcategories || []).filter(cat => {
        return (cat.options || []).filter(option => option.checked).length > 0
      })
    }
  }
}
