<template>
  <div class="color-input-container" :title="t('admin.options.fields.color')">
    <!-- A fake color picker input, because we initialize the real one only on click -->
    <div class="sp-replacer sp-light" @click.stop="initPicker">
      <div class="sp-preview">
        <div class="sp-preview-inner" :style="{'background-color': value }"></div>
      </div>
    </div>

    <input class="hidden" :value="value" ref="input"/>
  </div>
</template>

<script>
export default {
  props: ['value'],
  computed: {
    palette() {
      const palette = [
        ["#f4cccc","#fce5cd","#fff2cc","#d9ead3","#d0e0e3","#cfe2f3","#d9d2e9","#ead1dc"],
        ["#ea9999","#f9cb9c","#ffe599","#b6d7a8","#a2c4c9","#9fc5e8","#b4a7d6","#d5a6bd"],
        ["#e06666","#f6b26b","#ffd966","#93c47d","#76a5af","#6fa8dc","#8e7cc3","#c27ba0"],
        ["#cc0000","#e69138","#f1c232","#6aa84f","#45818e","#3d85c6","#674ea7","#a64d79"],
        ["#990000","#b45f06","#bf9000","#38761d","#134f5c","#0b5394","#351c75","#741b47"],
        ["#660000","#783f04","#7f6000","#274e13","#0c343d","#073763","#20124d","#4c1130"],
        ["#000000","#444444","#5b5b5b","#999999","#bcbcbc","#eeeeee","#f3f6f4","#ffffff"],
        ["#cb3626","#7e3200","#bd720e","#1e8065","#009a9c","#00537e","#8e36a5","#ab0061"],
        ["#f44336","#744700","#ce7e00","#8fce00","#2986cc","#16537e","#6a329f","#c90076"]
      ]

      var allColors = [];
      for(var i = 0; i < palette.length; i++) {
        allColors = allColors.concat(palette[i]);
      }

      let newColors = []
      let colorsInUse = this.$root.getAllItems().map(i => i.color)
        .concat(this.$root.getAllItems().map(i => i.softColor))
      colorsInUse.forEach((color) => {
        if (color && !allColors.includes(color.toLowerCase())) {
          newColors.push(color.toLowerCase())
          allColors.push(color.toLowerCase())
        }
        if (newColors.length == 8) {
          palette.push(newColors)
          newColors = []
        }
      })
      if (newColors.length > 0) palette.push(newColors)
      return palette
    }
  },
  methods: {
    initPicker() {
      this.$root.closeAllPopups()
      $(this.$refs.input).spectrum({
        type: "color",
        palette: this.palette,
        selectionPalette: [],
        showSelectionPalette: true,
        hideAfterPaletteSelect: true,
        showPalette: true,
        showAlpha: true,
        showInitial: true,
        preferredFormat: "hex",
        showInput: true,
        allowEmpty: true
      }).change((event) => {
        this.$emit('input', event.target.value)
      })
      this.$nextTick(() => {
        setTimeout(() => {
          $(this.$refs.input).spectrum("show")
        }, 0)
      })
    }
  }
}

</script>

<style lang='scss'>
  .sp-replacer {
    padding: 5px !important;
    border: none !important;
    background: none !important;
    width: auto !important;
    height: auto !important;

    .sp-preview {
      width: 15px;
      height: 15px;
      border: none;
      border-radius: 50%;
      margin-right: 0 !important;

      .sp-preview-inner {
        border-radius: 50%;
      }
    }
    .sp-dd { display: none; }
  }
  .color-input-container {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
  }
  .control-label .color-input-container {
    justify-content: flex-start;
    margin-left: -7px;
  }
  // We hide the color picker input, because we already display the fake one
  // The real one is only used by the lib to positionate the picker popup
  input.hidden + .sp-replacer {
    width: 0 !important;
    overflow: hidden;
    padding: 0 !important;
    visibility: hidden;
    position: absolute;
    left: 0;
    bottom: 0;
  }
</style>