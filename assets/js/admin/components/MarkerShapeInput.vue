<template>
  <div class="marker-shape-picker" @click.stop>
    <template v-if="displayTextInput">
      <div class="input-group" style="width: 100%">
        <span class="input-group-addon" v-show="selectedShape.name">
          <div class="addon-shape-container">
            <marker-shape :shape="selectedShape" :circle="circle"></marker-shape>
          </div>
        </span>
        <input class="form-control" @click="toggle()" type="text" :value="shapeValue"
              :placeholder="displayDefaultOption ? t('admin.options.fields.markerShape_choices.default') : ''"
              @input="selectedShape = shapes.find(s => s.name == $event.target.value) || {}" />
      </div>
    </template>
    <template v-else>
      <marker-shape :value="shapeValue || defaultValue" :shapes="shapes" @click="toggle()"
                    :title="t('admin.options.fields.marker')"></marker-shape>
    </template>

    <div class="picker-container" v-if="open">
      <div style="margin-bottom: 1rem; margin-left: 1rem;">
        <button type="button" class="close" @click="open = false">
          <span aria-hidden="true">&times;</span>
        </button>

        <slot></slot>

        <template v-if="displayDefaultOption">
          <label class="control-label checkbox-label">
            <input type="checkbox" v-model="useDefault" :disabled="!shapeValue" />
            <span>{{ t('admin.options.fields.markerShape_choices.default') }}</span>
            <a href="#" @click.stop.prevent="openMarkerConfig" style="margin-left: 8px">
              {{ t('admin.options.fields.markerShape_choices.change_default') }}
            </a>
          </label>
        </template>

        <label class="control-label checkbox-label">
          <input type="checkbox" v-model="circle"/>
          <span>{{ t('admin.options.fields.markerShape_choices.withCircle') }}</span>
        </label>

      </div>

      <div class="shape-container">
        <marker-shape v-for="shape in shapes" :key="shape.name" :circle="circle"
                      :shape="shape" @click="selectedShape = shape; open = false">
        </marker-shape>
      </div>
    </div>
  </div>
</template>

<script>
import MarkerShape from './MarkerShape.vue'

export default {
  components: { MarkerShape },
  props: ['value', 'displayDefaultOption', 'displayTextInput', 'defaultValue'],
  data() { 
    return {
      selectedShape: {},
      circle: false,
      open: false,
      useDefault: true,
      shapes: [
        // copied from gogocartoJs
        { name: "waterdrop", class: "gogo-icon-marker" },
        { name: "waterdrop-thin", class: "fa fa-map-marker" },

        { name: "sign", class: "fas fa-map-signs", iconInside: false },
        { name: "pin", class: "fas fa-map-pin", iconInside: false },
        { name: "thumbtack", class: "gogo-icon-stamp-1", iconInside: false },
        { name: "thunderbolt", class: "fas fa-bolt", iconInside: false },
        { name: "dollar", class: "fas fa-dollar-sign", iconInside: false },
        { name: "exclamation", class: "fas fa-exclamation", iconInside: false },
        { name: "heart", class: "fas fa-heart", iconInside: false },
        { name: "feather", iconInside: false },

        { name: "fun-animal", transform: "translateY(6.5px)", size: 1.15 },
        { name: "fun-animal-2", transform: "translateY(6.5px)", size: 1 },
        { name: "fun-egg", transform: "translateY(3px)", size: 1.15 },
        { name: "fun-flower", transform: "translateY(6px)", size: 1.4 },
        { name: "fun-hair", transform: "translateY(6px)", size: 1.15 },
        { name: "fun-hair-2", transform: "translateY(6px)", size: 1.2 },
        { name: "fun-hat", transform: "translateY(6px)", size: 1.05 },
        { name: "fun-hat-2", transform: "translateY(6px)", size: 1.25 },
        { name: "handsup-flower", transform: "translateY(2px)", size: 1.25 },
        { name: "handsup-hand", transform: "translateY(-8px) translateX(-10px)", size: 1.3 },
        { name: "handsup-heart", transform: "translateY(-1px) translateX(1px)", size: 1.2 },
        { name: "handsup-hotairballoon", transform: "translateY(-6px)", size: 1.25 },
        { name: "handsup-lollipop", transform: "translateY(4px) translateX(1px)", size: 1.05 },
        { name: "handsup-mushroom", transform: "translateY(-6px)", size: 1.2 },
        { name: "handsup-tree", transform: "translateY(4px) translateX(-1px)", size: 1.25 },
        { name: "shape-balloon", transform: "translateY(6.5px)", size: 1 },
        { name: "shape-crest", transform: "translateY(9px)", size: 1 },
        { name: "shape-diamond", transform: "translateY(0px)", size: 1.2 },
        { name: "shape-eye", transform: "translateY(8.5px)", size: 1.5 },
        { name: "shape-flower", transform: "translateY(-15px)", size: 1.4 },
        { name: "shape-flower-2", transform: "translateY(3px)", size: 1.2 },
        { name: "shape-hotairballoon", transform: "translateY(4px)", size: 1 },
        { name: "shape-leaf", transform: "translateY(12px)", size: 1.3 },
        { name: "shape-lollipop", transform: "translateY(4px)", size: 1.05 },
        { name: "shape-mushroom", transform: "translateY(-2px)", size: 1.15 },
        { name: "shape-panel", transform: "translateY(-3px) translateX(-2px)", size: 1.25 },
        { name: "shape-round", transform: "translateY(-3px)", size: 1.15 },
        { name: "shape-round-2", transform: "translateY(-3px)", size: 1.15 },
        { name: "shape-square", transform: "translateY(7px)", size: 1.1 },
        { name: "shape-square-2", transform: "translateY(0px)", size: 1.25 },
        { name: "shape-star", transform: "translateY(-1px)", size: 1.4 },
        { name: "shape-triangle", transform: "translateY(6px)", size: 1.2 },
        { name: "shape-triangleball", transform: "translateY(-5px) translateX(5px)", size: 1.2 },

        { name: "drawing-star", transform: "translateX(-2px) translateY(5px)", size: 1.3 },
        { name: "drawing-apple", size: 1.15 },
        { name: "drawing-cloud", transform: "translateY(3px) translateX(3px)", size: 1.4 },
        { name: "drawing-house", size: 1.1 },
        { name: "drawing-mountain", transform: "translateY(6px)", size: 1.25 },
      ]
    } 
  },
  mounted() {
    this.updateShapeFromValue()

    // Close picker when clicking outside
    window.addEventListener('click', (e) => {
        const picker = document.querySelector('.marker-shape-picker')
        if (this.open && !picker.contains(e.target)) {
            this.open = false
        }
    });
  },
  computed: {
    shapeValue() {
        if (!this.selectedShape.name) return ''
        return this.selectedShape.name + (this.circle ? '-circle' : '');
    }
  },
  watch: {
    shapeValue() {
      this.$emit('input', this.shapeValue)
    },
    value() {
      this.updateShapeFromValue()
    },
    useDefault() {
      if (this.useDefault) this.$emit('input', '')
      this.open = false
    },
  },
  methods: {
    toggle() {
      if (this.open) {
        this.open = false
      } else {
        if (this.$root.hasOwnProperty('closeAllPopups')) this.$root.closeAllPopups()
        this.open = true
      }
    },
    updateShapeFromValue() {
      this.useDefault = !this.value
      let shapeName = (this.value || "").split('-circle')[0]
      this.selectedShape = this.shapes.find(shape => shape.name == shapeName) || {}
      this.circle = (this.value || "").includes("-circle")
    },
    openMarkerConfig() {
      const href = $('.sidebar').find('a[href*=configuration-marker]').attr('href')
      window.open(href, '_blank')
    }
  }
}
</script>

<style lang='scss' scoped>
  .marker-shape-picker {
    position: relative;
  }
  .marker-shape-picker .input-group-addon {
    padding: 0;
    width: 40px;
  }
  .picker-container {
    position: absolute;
    padding: 1rem;
    width: 100%;

    min-width: 640px;
    max-height: 600px;
    box-shadow: 0 0 0 1px rgb(99 114 130 / 16%), 0 8px 16px rgb(27 39 51 / 8%);
    border-radius: 4px;
    z-index: 50;
    background: white;
    overflow: auto;
    h4 {
      margin-top: 5px;
      margin-bottom: 15px;
    }
    hr {
      margin: 15px 0;
    }
  }
  #taxonomy-tree .picker-container {
    margin-top: -12px;
  }
  .picker-container .btn-close {
    float: right;
    cursor: pointer;
    padding: 6px;
    background: #eee;
    border-radius: 50%;
    width: 28px;
    text-align: center;
  }
  .shape-container {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }
  .addon-shape-container {
    position: absolute;
    top: 7px;
    left: -4px;
  }
  .addon-shape-container .shape-item {
    transform: scale(.4);
    transform-origin: top;
  }
</style>