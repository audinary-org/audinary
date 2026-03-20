<template>
  <div
    class="flag-container inline-flex items-center justify-center overflow-hidden"
    :class="[
      sizeClasses,
      rounded ? 'rounded-full' : 'rounded-md',
      shadow ? 'shadow-sm' : '',
    ]"
  >
    <span
      :class="`fi fi-${countryCode.toLowerCase()}`"
      :style="{ fontSize: iconSize }"
      class="flag-icon leading-none"
    ></span>
  </div>
</template>

<script>
export default {
  name: "FlagIcon",
  props: {
    countryCode: {
      type: String,
      required: true,
      validator: (value) => /^[a-z]{2}$/i.test(value),
    },
    size: {
      type: String,
      default: "md",
      validator: (value) => ["xs", "sm", "md", "lg", "xl"].includes(value),
    },
    rounded: {
      type: Boolean,
      default: true,
    },
    shadow: {
      type: Boolean,
      default: true,
    },
  },
  computed: {
    sizeClasses() {
      const sizes = {
        xs: "w-4 h-4",
        sm: "w-6 h-6",
        md: "w-8 h-8",
        lg: "w-10 h-10",
        xl: "w-12 h-12",
      };
      return sizes[this.size] || sizes.md;
    },
    iconSize() {
      const sizes = {
        xs: "1rem",
        sm: "1.25rem",
        md: "1.5rem",
        lg: "2rem",
        xl: "2.5rem",
      };
      return sizes[this.size] || sizes.md;
    },
  },
};
</script>

<style scoped>
.flag-container {
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(8px);
}

.flag-icon {
  display: block !important;
  width: 100% !important;
  height: 100% !important;
  object-fit: cover;
}

/* Ensure flags maintain aspect ratio */
.fi {
  background-size: cover !important;
  background-position: center !important;
  background-repeat: no-repeat !important;
}
</style>
