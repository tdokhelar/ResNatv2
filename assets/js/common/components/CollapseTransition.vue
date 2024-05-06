<template>
  <!-- Code from https://www.npmjs.com/package/vue-collapse-transition -->
  <transition
    @beforeEnter="beforeEnter"
    @enter="enter"
    @afterEnter="afterEnter"
    @beforeLeave="beforeLeave"
    @leave="leave"
    @afterLeave="afterEnter"
  >
    <slot />
  </transition>
</template>

<script>
export default {
  props: ['disabled'],
  data() {
    return {
      transition_className: "collapse-transition"
    };
  },
  methods: {
    beforeEnter(ele) {
      if (this.disabled) return

      ele.classList.add(this.transition_className);
      if (!ele.dataset) ele.dataset = {};
      ele.dataset.oldPaddingTop = ele.style.paddingTop;
      ele.dataset.oldPaddingBottom = ele.style.paddingBottom;

      Object.assign(ele.style, {
        height: "0",
        paddingTop: "0",
        paddingBottom: "0"
      });
    },

    enter(ele) {
      if (this.disabled) return

      ele.dataset.oldOverflow = ele.style.overflow;
      if (ele.scrollHeight !== 0) {
        Object.assign(ele.style, {
          height: ele.scrollHeight + "px",
          paddingTop: ele.dataset.oldPaddingTop,
          paddingBottom: ele.dataset.oldPaddingBottom
        });
      } else {
        Object.assign(ele.style, {
          height: "",
          paddingTop: ele.dataset.oldPaddingTop,
          paddingBottom: ele.dataset.oldPaddingBottom
        });
      }

      ele.style.overflow = "hidden";
    },

    afterEnter(ele) {
      if (this.disabled) return
      // for safari: remove class then reset height is necessary
      ele.classList.remove(this.transition_className);
      ele.style.height = "";
      ele.style.overflow = ele.dataset.oldOverflow;
    },

    beforeLeave(ele) {
      if (this.disabled) return

      if (!ele.dataset) ele.dataset = {};
      ele.dataset.oldPaddingTop = ele.style.paddingTop;
      ele.dataset.oldPaddingBottom = ele.style.paddingBottom;
      ele.dataset.oldOverflow = ele.style.overflow;

      ele.style.height = ele.scrollHeight + "px";
      ele.style.overflow = "hidden";
    },

    leave(ele) {
      if (this.disabled) return

      if (ele.scrollHeight !== 0) {
        // for safari: add class after set height, or it will jump to zero height suddenly, weired
        ele.classList.add(this.transition_className);

        Object.assign(ele.style, {
          height: "0",
          paddingTop: "0",
          paddingBottom: "0"
        });
      }
    },

    afterLeave(ele) {
      if (this.disabled) return

      ele.classList.remove(this.transition_className);
      Object.assign(ele.style, {
        height: "",
        overflow: ele.dataset.oldOverflow,
        paddingTop: ele.dataset.oldPaddingTop,
        paddingBottom: ele.dataset.oldPaddingBottom
      });
    }
  }
};
</script>

<style scoped>
.collapse-transition {
  transition: .2s height ease-in-out, .2s padding-top ease-in-out,
    .2s padding-bottom ease-in-out,
    .2s margin-top ease-in-out,
    .2s margin-bottom ease-in-out;
}
</style>