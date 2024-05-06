<template>
  <div class="shape-item" @click="$emit('click')" :class="internalShape.name">
    <i v-if="internalShape.class" :class="internalShape.class"></i>
    <img v-else :src="`/markers/${internalShape.name}.svg`" :style="`transform: scale(${internalShape.size});`"></img>
    <div v-if="internalShape.iconInside !== false && internalCircle" class="marker-circle" :style="{transform: internalShape.transform}"></div>
  </div>
</template>

<script>
export default {
  // We can either provide the string value, or the shape and circle
  props: {
    value: {
      type: String,
      default: ""
    },
    shapes: {
      type: Array
    },
    shape: {
      type: Object,
      default() { return {} }
    },
    circle: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      internalShape: {},
      internalCircle: false
    }
  },
  mounted() {
    this.internalShape = this.shape
    this.internalCircle = this.circle
    this.updateShapeFromValue()
  },
  methods: {
    updateShapeFromValue() {
      if (!this.value) return
      let shapeName = this.value.split('-circle')[0]
      this.internalShape = this.shapes.find(shape => shape.name == shapeName) || {}
      this.internalCircle = this.value.includes("-circle")
    },
  },
  watch: {
    value() {
      this.updateShapeFromValue()
    },
    shape() {
      this.internalShape = this.shape
    },
    circle() {
      this.internalCircle = this.circle
    }
  }
}
</script>

<style lang='scss' scoped>
  .shape-item {
    display: flex;
    border-radius: 3px;
    justify-content: center;
    text-align: center;
    transform: scale(.7);
    &.waterdrop-thin .marker-circle {
      transform: scale(.9) translateY(4px);
    }
  }
  .shape-item:hover {
    cursor: pointer;
    background-color: #eee;
  }
  .shape-item i, .shape-item img {
    width: 50px;
    height: 50px;
    line-height: 50px;
    font-size: 50px;
    transform-origin: bottom;
    color: black;
    opacity: .9;
  }
  .marker-circle {
    width: 32px;
    height: 32px;
    font-size: 22px;
    line-height: 34px;
    border-radius: 50%;
    background-color: white;
    position: absolute;
    transform: translateY(4px);
  }
</style>