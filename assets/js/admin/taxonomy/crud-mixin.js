export default {
  methods: {
    generateId() { 
      return "NEW_" + Math.floor(Math.random() * 100000) 
    },
    deleteNode(node) {
      if (node.type == "Option")
        this.$root.deletedOptionIds.push(node.id)
      else
        this.$root.deletedCategoryIds.push(node.id)

      if (node.type == "Group")
        this.$root.mainOption.subcategories = this.$root.mainOption.subcategories.filter(cat => cat.id != node.id)
      for (let category of this.$root.mainOption.subcategories)
        this.recursiveDeleteNode(category, node)
    },
    recursiveDeleteNode(category, node) {
      for (let option of category.options) {
        if (node.type == "Option")
          category.options = category.options.filter(opt => opt.id != node.id)
        for (let subcategory of option.subcategories) {
          if (node.type == "Group")
            option.subcategories = option.subcategories.filter(cat => cat.id != node.id)
          this.recursiveDeleteNode(subcategory, node)
        }
      }
    },
    updatePosition(parent, children) {
      if (!children) return
      children.forEach((item, i) => {
        if (item.index != i) {
          item.index = i
          item.edited = true
        }
        if (item.parentId != parent.id) {
          item.parentId = parent.id
          item.edited = true
        }
      })
    }
  }
}