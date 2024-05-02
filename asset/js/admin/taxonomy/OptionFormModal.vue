<template>
  <Modal :title="t('admin.commons.actions.edit_model', { model: t('admin.options._label') })" ref="modal">
    <AdminForm :model="model" :model-name="modelName">
      <AdminGroup label="advanced">
        <div class="row">
          <div class="col-md-6">
            <AdminField field="nameShort" type="text"/>
            <AdminField field="customId" type="text"/>
            <AdminField field="textHelper" type="text"/>
            <AdminField field="url" type="text"/>
          </div>
          <div class="col-md-6">
            <AdminField field="softColor" type="color"/>
            <hr/>
            <AdminField field="useIconForMarker" type="checkbox"/>
            <AdminField field="useColorForMarker" type="checkbox"/>
            <hr/>
            <AdminField field="showExpanded" type="checkbox"/>
            <AdminField field="unexpandable" type="checkbox"/>
          </div>
        </div>
      </AdminGroup>


      <AdminGroup label="description">
        <AdminField field="enableDescription" type="checkbox" style="margin-bottom: 10px"/>
        <AdminField field="descriptionLabel" type="text"/>
      </AdminGroup>

      <AdminGroup label="osm">
        <AdminField field="osmTags" type="osm-tags"/>
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