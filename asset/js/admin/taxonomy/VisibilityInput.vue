<template>
  <div class="dropdown visibility-btn" ref="dropdown"
        @click.stop="show" :title="t('admin.options.form.visibility')">

    <span class="dropdown-btn" :class="iconClass" data-toggle="dropdown"></span>

    <ul class="dropdown-menu" @click.stop>
      <button type="button" class="close" @click="hide">
        <span aria-hidden="true">&times;</span>
      </button>
      <AdminForm :model="model" :model-name="modelName">
        <div class="dropdown-title">{{ t(`admin.${this.modelName}.form.groups.display`) }}</div>
        <AdminField field="displayInMenu" type="checkbox"/>
        <AdminField field="displayInInfoBar" type="checkbox"/>
        <AdminField field="displayInForm" type="checkbox"/>
        <template v-if="modelName == 'options'">
          <div class="dropdown-title">{{ t(`admin.${this.modelName}.form.groups.displayChildren`) }}</div>
          <AdminField field="displayChildrenInMenu" type="checkbox"/>
          <AdminField field="displayChildrenInInfoBar" type="checkbox"/>
          <AdminField field="displayChildrenInForm" type="checkbox"/>
        </template>
      </AdminForm>
    </ul>
  </div>
</template>

<script>
import AdminField from '../components/AdminField.vue'
import AdminForm from '../components/AdminForm.vue'

export default {
  components: { AdminField, AdminForm },
  props: ['model', 'modelName'],
  computed: {
    iconClass() {
      let placesDisplayed = 0
      if (this.model.displayInInfoBar) placesDisplayed += 1
      if (this.model.displayInMenu) placesDisplayed += 1
      if (this.model.displayInForm) placesDisplayed += 1
      switch (placesDisplayed) {
        case 0:
          return "far fa-eye-slash disabled"
        case 3:
          return "far fa-eye"
        default:
          return "far fa-eye disabled"
      }
    }
  },
  methods: {
    show() {
      // We do not use a Vue prop to control openning/closing because
      // bootstrap dropdown behaviour outside of Vue. So when clicking somewhere
      // the dropdown is closed, without the Vue prop being updated
      if (this.$refs.dropdown.classList.contains('open')) {
        this.$refs.dropdown.classList.remove('open')
      } else {
        // fake click so other dropdown closes before the new one open
        // we need that because we stop propagation on the click event, so
        // other dropdown do not get close by the click event
        this.$root.closeAllPopups()
        this.$refs.dropdown.classList.add('open')
      }
    },
    hide() {
      this.$refs.dropdown.classList.remove('open')
    }
  }
}
</script>

<style lang='scss'>
  .visibility-btn {
    width: 3rem;
    padding: 5px;
    margin-right: 1rem;
    align-items: center;
    justify-content: center;
    display: inline-flex;
    cursor: pointer;
    &:hover > .dropdown-btn {
      opacity: 1;
    }
    .dropdown-btn {
      opacity: .8;
      &.disabled {
        opacity: .4;
      }
    }
    .dropdown-menu {
      cursor: default;
      color: #222d32;
      padding: 10px;
      min-width: 200px;
      .dropdown-title {
        margin-bottom: 5px;
        font-weight: bold;
        &:not(:first-child) {
          margin-top: 10px;
        }
      }
      .control-label {
        font-weight: normal;
      }
    }
  }
</style>