<template>
  <Modal :title="t('admin.commons.actions.edit_model', { model: t('admin.categories._label') })" ref="modal">
    <AdminForm :model="model" :model-name="modelName">
      <AdminGroup custom-key="admin.options.form.groups.advanced">
        <div class="row">
          <div class="col-md-6">
            <AdminField field="pickingOptionText" type="text"/>
            <AdminField field="nameShort" type="text"/>
            <AdminField field="customId" type="text"/>
          </div>
          <div class="col-md-6">
            <AdminField field="isMandatory" type="checkbox"/>
            <AdminField field="singleOption" type="checkbox"/>
            <hr/>
            <AdminField field="showExpanded" type="checkbox"/>
            <AdminField field="unexpandable" type="checkbox"/>
            <AdminField field="displaySuboptionsInline" type="checkbox"/>
          </div>
        </div>
      </AdminGroup>

      <AdminGroup custom-key="admin.options.form.groups.description">
        <AdminField field="enableDescription" type="checkbox" style="margin-bottom: 10px"/>
        <AdminField field="descriptionLabel" type="text" disable-by="enableDescription" />
      </AdminGroup>
    </AdminForm>
  </Modal>
</template>

<script>
import Modal from '../components/Modal.vue'
import AdminField from '../components/AdminField.vue'
import AdminGroup from '../components/AdminGroup.vue'
import AdminForm from '../components/AdminForm.vue'

export default {
  components: { Modal, AdminField, AdminGroup, AdminForm },
  data() {
    return {
      model: {},
      modelName: ''
    }
  },
  methods: {
    edit(modelName, model) {
      this.model = model
      this.modelName = modelName
      this.$refs.modal.show()
    }
  }
}
</script>

<style lang='scss' scoped>
</style>