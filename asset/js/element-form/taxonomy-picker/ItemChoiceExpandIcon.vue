<template>
  <span class="expand-container">
    <span class="invalid-icon fa fa-exclamation"></span>
    <span class="expand-icon fa"></span>
  </span>
</template>

<script>
export default {
}
</script>

<style lang='scss' scoped>
  .expand-container {
    text-align: right;
  }
  .category-choice > .content .expand-container {
    width: 1em;
  }
  .option-choice > .content .expand-container {
    width: 2em;
    margin-left: auto;
  }
  .expand-icon, .invalid-icon {
    font-size: 0.75em;
    width: .8em;
    text-align: right;
  }

  .expand-icon {
    &.active {
      color: var(--color-primary);
    }
    // redeclare font-awesome class so we can use @extend, then icons can be easily changed
    &.chevron-down:before { content: "\f078"; }
    &.chevron-right:before { content: "\f054"; }
    &.caret-down:before { content: "\f0d7"; }
    &.caret-right:before { content: "\f0da"; }
  }

  .item-choice .title:hover .expand-icon {
    @extend .active;
  }

  // Block display : caret for category, chevron for option
  // icon goes down when children expanded
  .category-choice > .content.child-display-block .expand-icon {
    @extend .caret-right;
  }
  .category-choice > .content.child-display-block.expanded .expand-icon {
    @extend .caret-down;
  }
  .option-choice > .content.child-display-block .expand-icon {
    @extend .chevron-right;
  }
  .option-choice > .content.child-display-block.expanded .expand-icon {
    @extend .chevron-down;
  }

  // Floating display : always chevron right, right aligned
  .option-choice > .content.child-display-floating .expand-icon {
    @extend .chevron-right;
  }
  .category-choice > .content.child-display-floating .expand-icon {
    @extend .caret-down;
  }
  .item-choice > .content.child-display-floating .expand-container {
    margin-left: auto;
  }
  .item-choice > .content.child-display-floating.expanded .expand-icon {
    @extend .active;
  }

  .invalid-icon {
    display: none;
    color: var(--color-error);
  }
  .option-choice > .content.invalid {
    .invalid-icon {
      display: inline-block;
    }
    .expand-icon {
      color: var(--color-error) !important;
    }
  }
</style>