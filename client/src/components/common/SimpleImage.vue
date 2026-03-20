<template>
  <img
    v-if="!hasError"
    :src="imageUrl"
    :alt="alt"
    :class="[props.class, $attrs.class]"
    :style="style"
    :loading="loading"
    @error="handleError"
  />
  <img
    v-else
    :src="placeholderUrl"
    :alt="alt"
    :class="[props.class, $attrs.class]"
    :style="style"
    :loading="loading"
  />
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";

// Inherit attrs (like :class) to allow dynamic class binding
defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  imageType: {
    type: String,
    required: true,
    validator: (value) =>
      [
        "album",
        "artist",
        "profile",
        "playlist",
        "album_thumbnail",
        "artist_thumbnail",
      ].includes(value),
  },
  imageId: {
    type: String,
    required: false,
  },
  size: {
    type: String,
    default: "medium",
  },
  alt: {
    type: String,
    default: "",
  },
  class: {
    type: String,
    default: "",
  },
  style: {
    type: [String, Object],
    default: "",
  },
  loading: {
    type: String,
    default: "lazy",
  },
  placeholder: {
    type: String,
    default: "image",
  },
  placeholderSize: {
    type: String,
    default: "40px",
  },
});

const hasError = ref(false);

const placeholderUrl = "/img/placeholder_audinary.png";

const style = computed(() => {
  if (typeof props.style === "object") {
    return props.style;
  }
  return props.style;
});

const imageUrl = computed(() => {
  if (!props.imageId) return null;

  let suffix = "";
  if (
    props.size === "thumbnail" &&
    (props.imageType === "album" || props.imageType === "playlist")
  ) {
    suffix = "_thumbnail";
  }

  switch (props.imageType) {
    case "album":
      return `/img/userdata/albums/${props.imageId}${suffix}.webp`;
    case "album_thumbnail":
      return `/img/userdata/albums/${props.imageId}_thumbnail.webp`;
    case "artist":
      return `/img/userdata/artists/${props.imageId}.webp`;
    case "artist_thumbnail":
      return `/img/userdata/artists/${props.imageId}_thumbnail.webp`;
    case "profile":
      return `/img/userdata/profiles/${props.imageId}.webp`;
    case "playlist":
      return `/img/userdata/playlists/${props.imageId}${suffix}.webp`;
    default:
      return null;
  }
});

const handleError = () => {
  hasError.value = true;
};
</script>
