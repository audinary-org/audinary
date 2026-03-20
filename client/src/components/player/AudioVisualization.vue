<template>
  <div ref="canvasContainer" class="w-full h-full relative">
    <canvas ref="canvas" class="w-full h-full"></canvas>
    <div
      v-if="showControls"
      class="absolute bottom-4 right-4 flex flex-col gap-2"
    >
      <button
        v-for="(effect, index) in effects"
        :key="effect.name"
        @click="switchEffect(index)"
        :class="[
          'px-3 py-2 rounded-lg text-sm font-medium transition-all backdrop-blur-lg',
          currentEffect === index
            ? 'bg-blue-500/80 text-white shadow-lg'
            : 'bg-white/10 text-white/80 hover:bg-white/20',
        ]"
      >
        {{ effect.name }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import * as THREE from "three";

const props = defineProps({
  audioAnalyser: {
    type: Object,
    default: null,
  },
  isPlaying: {
    type: Boolean,
    default: false,
  },
  showControls: {
    type: Boolean,
    default: true,
  },
  logoUrl: {
    type: String,
    default: null,
  },
});

const canvasContainer = ref(null);
const canvas = ref(null);
const currentEffect = ref(0);

// Three.js objects
let scene, camera, renderer, sphere, logo, animationId;
let sphereGeometry, sphereMaterial, frequencyRings;

// Audio visualization data
const frequencyData = ref(new Uint8Array(256));
const bassData = ref(0);
const midData = ref(0);
const trebleData = ref(0);

// Color system for dynamic changes
const colorSchemes = [
  { primary: 0x00ff88, secondary: 0xff0040, tertiary: 0x0040ff }, // Green-Red-Blue
  { primary: 0xff6b00, secondary: 0x00ffff, tertiary: 0xff00ff }, // Orange-Cyan-Magenta
  { primary: 0xff0080, secondary: 0x80ff00, tertiary: 0x0080ff }, // Pink-Lime-Blue
  { primary: 0xffff00, secondary: 0xff0000, tertiary: 0x8000ff }, // Yellow-Red-Purple
  { primary: 0x00ff00, secondary: 0xff4000, tertiary: 0x4000ff }, // Green-Orange-Violet
  { primary: 0xff8000, secondary: 0x00ff80, tertiary: 0x8000ff }, // Orange-Teal-Purple
];

const currentColorScheme = ref(0);
const colorChangeTimer = ref(0);
const nextColorChange = ref(Math.random() * 10000 + 5000); // Random between 5-15 seconds

// Effects configurations
const effects = [
  {
    name: "Wireframe",
    type: "wireframe",
    bassReactive: true,
    particles: false,
    wireframe: true,
  },
  {
    name: "Frequency Rings",
    type: "rings",
    bassReactive: true,
    particles: false,
    wireframe: true,
  },
  {
    name: "Pulsing Edge",
    type: "edge",
    bassReactive: true,
    particles: false,
    wireframe: true,
  },
  {
    name: "NCS Style",
    type: "ncs",
    bassReactive: true,
    particles: false,
    wireframe: true,
  },
];

function initThreeJS() {
  if (!canvas.value) return;

  // Scene
  scene = new THREE.Scene();

  // Camera
  camera = new THREE.PerspectiveCamera(75, 1, 0.1, 1000);
  camera.position.z = 5;

  // Renderer
  renderer = new THREE.WebGLRenderer({
    canvas: canvas.value,
    antialias: true,
    alpha: true,
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setClearColor(0x000000, 0);

  // Create reactive sphere
  createReactiveSphere();

  // Create rotating logo
  if (props.logoUrl) {
    createRotatingLogo();
  }

  // Lights
  const ambientLight = new THREE.AmbientLight(0x404040, 0.4);
  scene.add(ambientLight);

  const directionalLight = new THREE.DirectionalLight(0xffffff, 0.6);
  directionalLight.position.set(5, 5, 5);
  scene.add(directionalLight);

  // Start animation
  animate();

  // Handle resize
  handleResize();
  window.addEventListener("resize", handleResize);
}

function createReactiveSphere() {
  // Create smaller sphere that reacts to bass - like NCS style
  sphereGeometry = new THREE.SphereGeometry(1.2, 32, 32);

  // Store original positions for morphing
  const originalPositions = sphereGeometry.attributes.position.array.slice();
  sphereGeometry.userData.originalPositions = originalPositions;

  // Wireframe material with glow effect - NCS style
  sphereMaterial = new THREE.MeshBasicMaterial({
    color: 0x00ff88,
    transparent: true,
    opacity: 0.8,
    wireframe: true, // Always wireframe
    wireframeLinewidth: 1,
  });

  sphere = new THREE.Mesh(sphereGeometry, sphereMaterial);
  scene.add(sphere);

  // Create frequency rings around the sphere
  createFrequencyRings();
}

function createFrequencyRings() {
  frequencyRings = new THREE.Group();

  // Create 3 rings for bass, mid, treble with dynamic colors - around flame circle
  const ringData = [
    { radius: 1.8, name: "bass", colorKey: "secondary" },
    { radius: 2.4, name: "mid", colorKey: "primary" },
    { radius: 3.0, name: "treble", colorKey: "tertiary" },
  ];

  const scheme = colorSchemes[currentColorScheme.value];

  ringData.forEach((data, index) => {
    const ringGeometry = new THREE.RingGeometry(
      data.radius - 0.05,
      data.radius + 0.05,
      64,
    );
    const ringMaterial = new THREE.MeshBasicMaterial({
      color: scheme[data.colorKey],
      transparent: true,
      opacity: 0.4,
      side: THREE.DoubleSide,
    });

    const ring = new THREE.Mesh(ringGeometry, ringMaterial);

    // Position rings at different angles
    ring.rotation.x = Math.PI / 2 + (index * Math.PI) / 6;
    ring.rotation.y = (index * Math.PI) / 3;

    ring.userData = {
      originalScale: 1,
      type: data.name,
      originalOpacity: 0.4,
      colorKey: data.colorKey,
      baseColor: scheme[data.colorKey],
    };

    frequencyRings.add(ring);
  });

  scene.add(frequencyRings);
}

function createRotatingLogo() {
  if (!props.logoUrl) return;

  const loader = new THREE.TextureLoader();
  loader.load(
    props.logoUrl,
    (texture) => {
      // Calculate aspect ratio of the texture
      const aspectRatio = texture.image.width / texture.image.height;

      const logoGeometry = new THREE.PlaneGeometry(1.5 * aspectRatio, 1.5);
      const logoMaterial = new THREE.MeshBasicMaterial({
        map: texture,
        transparent: true,
        alphaTest: 0.01,
        opacity: 1.0,
        // Add color correction for better visibility
        color: 0xffffff,
        // Disable depth testing so logo is always visible
        depthTest: false,
        depthWrite: false,
      });

      logo = new THREE.Mesh(logoGeometry, logoMaterial);
      logo.position.set(0, 0, 0); // Center position exactly at origin

      scene.add(logo);
    },
    undefined,
    (error) => {
      console.warn("Logo failed to load:", error);
      // Create fallback text logo
      createTextLogo();
    },
  );
}

function createTextLogo() {
  // Fallback: create a simple text-based logo
  const canvas = document.createElement("canvas");
  const context = canvas.getContext("2d");
  canvas.width = 512;
  canvas.height = 512;

  // Draw background circle with bright colors
  context.fillStyle = "rgba(0, 255, 136, 1.0)";
  context.beginPath();
  context.arc(256, 256, 200, 0, 2 * Math.PI);
  context.fill();

  // Add glow effect
  context.shadowColor = "rgba(0, 255, 136, 0.8)";
  context.shadowBlur = 20;
  context.fillStyle = "rgba(0, 255, 136, 1.0)";
  context.beginPath();
  context.arc(256, 256, 180, 0, 2 * Math.PI);
  context.fill();

  // Reset shadow
  context.shadowBlur = 0;

  // Draw text with better contrast
  context.fillStyle = "#ffffff";
  context.strokeStyle = "#000000";
  context.lineWidth = 3;
  context.font = "bold 72px Arial";
  context.textAlign = "center";
  context.textBaseline = "middle";
  context.strokeText("ICON", 256, 256);
  context.fillText("ICON", 256, 256);

  const texture = new THREE.CanvasTexture(canvas);
  const logoGeometry = new THREE.PlaneGeometry(1.2, 1.2);
  const logoMaterial = new THREE.MeshBasicMaterial({
    map: texture,
    transparent: true,
    opacity: 0.9,
  });

  logo = new THREE.Mesh(logoGeometry, logoMaterial);
  logo.position.set(0, 0, 0); // Center position exactly at origin
  scene.add(logo);
}

function updateAudioVisualization() {
  if (!props.audioAnalyser || !props.isPlaying) {
    // Gradually return to rest state
    bassData.value *= 0.95;
    midData.value *= 0.95;
    trebleData.value *= 0.95;
    return;
  }

  // Get frequency data from analyser
  props.audioAnalyser.getByteFrequencyData(frequencyData.value);

  // Calculate bass, mid, and treble averages
  const bufferLength = frequencyData.value.length;
  const bassRange = Math.floor(bufferLength * 0.1);
  const midRange = Math.floor(bufferLength * 0.3);

  let bassSum = 0;
  let midSum = 0;
  let trebleSum = 0;

  // Bass (0-10% of frequency range)
  for (let i = 0; i < bassRange; i++) {
    bassSum += frequencyData.value[i];
  }
  bassData.value = bassSum / bassRange / 255;

  // Mid (10-40% of frequency range)
  for (let i = bassRange; i < midRange; i++) {
    midSum += frequencyData.value[i];
  }
  midData.value = midSum / (midRange - bassRange) / 255;

  // Treble (40-100% of frequency range)
  for (let i = midRange; i < bufferLength; i++) {
    trebleSum += frequencyData.value[i];
  }
  trebleData.value = trebleSum / (bufferLength - midRange) / 255;

  // Update color scheme timer
  updateColorScheme();
}

function updateColorScheme() {
  colorChangeTimer.value += 16; // ~60fps

  // Random color changes based on audio intensity or time
  const audioIntensity =
    (bassData.value + midData.value + trebleData.value) / 3;

  // Trigger color change on strong bass hits or after time interval
  if (
    (bassData.value > 0.8 && Math.random() < 0.1) ||
    colorChangeTimer.value >= nextColorChange.value
  ) {
    // Switch to random color scheme
    const newScheme = Math.floor(Math.random() * colorSchemes.length);
    if (newScheme !== currentColorScheme.value) {
      currentColorScheme.value = newScheme;
      updateVisualizationColors();
    }

    // Reset timer with new random interval
    colorChangeTimer.value = 0;
    nextColorChange.value = Math.random() * 15000 + 8000; // 8-23 seconds
  }
}

function updateVisualizationColors() {
  const scheme = colorSchemes[currentColorScheme.value];

  // Update sphere color
  if (sphereMaterial) {
    sphereMaterial.color.setHex(scheme.primary);
  }

  // Update ring colors
  if (frequencyRings) {
    frequencyRings.children.forEach((ring, index) => {
      const userData = ring.userData;
      const newColor = scheme[userData.colorKey];
      userData.baseColor = newColor;
      ring.material.color.setHex(newColor);
    });
  }
}

function updateSphereReactivity() {
  if (!sphere || !sphereGeometry) return;

  // More bass-reactive scaling
  const bassIntensity = bassData.value;
  const midIntensity = midData.value;
  const trebleIntensity = trebleData.value;

  // Stronger bass reaction for sphere pulsing
  const scale = 1.0 + bassIntensity * 0.25; // Much stronger bass response
  sphere.scale.set(scale, scale, scale);

  // Update material color with stronger bass influence
  if (sphereMaterial) {
    const time = Date.now() * 0.001;
    const scheme = colorSchemes[currentColorScheme.value];
    const baseColor = new THREE.Color(scheme.primary);

    // Bass-heavy hue shifting - bass dominates color changes
    const audioHue =
      (bassData.value * 300 +
        midData.value * 60 +
        trebleData.value * 60 +
        time * 20) %
      360;
    const audioColor = new THREE.Color().setHSL(audioHue / 360, 0.9, 0.6);

    // Stronger bass influence on color mixing
    const bassWeight = bassIntensity * 0.8;
    const otherWeight = (midIntensity + trebleIntensity) * 0.2;
    const totalAudioIntensity = bassWeight + otherWeight;

    const finalColor = baseColor.clone().lerp(audioColor, totalAudioIntensity);

    // More frequent and stronger color bursts on bass
    if (bassData.value > 0.5 && Math.random() < 0.3) {
      const burstColor = new THREE.Color().setHSL(Math.random(), 1.0, 0.8);
      finalColor.lerp(burstColor, 0.6);
    }

    sphereMaterial.color.copy(finalColor);
    sphereMaterial.opacity = 0.5 + totalAudioIntensity * 0.5;
  }

  // Bass-reactive rotation - spins faster with bass
  sphere.rotation.y += 0.003 + bassIntensity * 0.02;
  sphere.rotation.x += 0.001 + bassIntensity * 0.01;

  // Update frequency rings
  updateFrequencyRings();
}

function updateFrequencyRings() {
  if (!frequencyRings) return;

  const rings = frequencyRings.children;
  const audioValues = [bassData.value, midData.value, trebleData.value];
  const time = Date.now() * 0.001; // Time in seconds

  rings.forEach((ring, index) => {
    const audioValue = audioValues[index] || 0;
    const userData = ring.userData;

    // Scale rings based on their frequency data
    const scale = 1 + audioValue * 0.4;
    ring.scale.set(scale, scale, 1);

    // Update opacity based on audio intensity
    ring.material.opacity = userData.originalOpacity + audioValue * 0.7;

    // Rotate rings at different speeds
    ring.rotation.z += 0.008 + audioValue * 0.03;

    // Dynamic color mixing - blend base color with audio-reactive hue
    const baseColor = new THREE.Color(userData.baseColor);
    const audioHue = (audioValue * 360 + time * 30 + index * 120) % 360;
    const audioColor = new THREE.Color().setHSL(audioHue / 360, 0.8, 0.6);

    // Blend base color with audio color based on intensity
    const blendFactor = audioValue * 0.7;
    const finalColor = baseColor.clone().lerp(audioColor, blendFactor);

    // Add random color spikes on high audio
    if (audioValue > 0.7 && Math.random() < 0.3) {
      const randomHue = Math.random() * 360;
      const spikeColor = new THREE.Color().setHSL(randomHue / 360, 1.0, 0.7);
      finalColor.lerp(spikeColor, 0.5);
    }

    ring.material.color.copy(finalColor);
  });

  // Rotate the entire ring group with audio reactivity
  const rotationSpeed =
    0.003 + (bassData.value + midData.value + trebleData.value) * 0.002;
  frequencyRings.rotation.y += rotationSpeed;
  frequencyRings.rotation.x += rotationSpeed * 0.5;
}

function updateLogoRotation() {
  if (!logo) return;

  // Bass-reactive rotation - spins faster with bass like a vinyl record
  logo.rotation.z += 0.005 + bassData.value * 0.03;

  // Strong bass scaling - logo pulses with bass
  const bassIntensity = bassData.value;
  const midIntensity = midData.value;
  const scale = 0.8 + (bassIntensity * 0.3 + midIntensity * 0.1); // More bass influence
  logo.scale.set(scale, scale, scale);

  // Keep logo bright and visible with bass-reactive brightness
  if (logo.material) {
    logo.material.opacity = 1.0;

    // Bass-heavy brightness enhancement
    const totalIntensity =
      bassIntensity * 0.7 + (midIntensity + trebleData.value) * 0.3;
    const brightness = 1.0 + totalIntensity * 0.4; // Stronger brightness changes
    logo.material.color.setScalar(brightness);
  }
}

function animate() {
  animationId = requestAnimationFrame(animate);

  // Update audio visualization data
  updateAudioVisualization();

  // Update sphere reactivity
  updateSphereReactivity();

  // Update logo
  updateLogoRotation();

  // Render
  renderer.render(scene, camera);
}

function switchEffect(index) {
  currentEffect.value = index;
  const effect = effects[index];

  if (sphereMaterial) {
    sphereMaterial.wireframe = effect.wireframe;
  }
}

function handleResize() {
  if (!canvasContainer.value || !renderer || !camera) return;

  const container = canvasContainer.value;
  const width = container.clientWidth;
  const height = container.clientHeight;

  camera.aspect = width / height;
  camera.updateProjectionMatrix();

  renderer.setSize(width, height);
}

function cleanup() {
  if (animationId) {
    cancelAnimationFrame(animationId);
  }

  window.removeEventListener("resize", handleResize);

  if (renderer) {
    renderer.dispose();
  }

  if (sphereGeometry) {
    sphereGeometry.dispose();
  }

  if (sphereMaterial) {
    sphereMaterial.dispose();
  }

  // Clean up frequency rings
  if (frequencyRings) {
    frequencyRings.children.forEach((ring) => {
      if (ring.geometry) ring.geometry.dispose();
      if (ring.material) ring.material.dispose();
    });
  }

  // Clean up logo
  if (logo) {
    if (logo.geometry) logo.geometry.dispose();
    if (logo.material) {
      if (logo.material.map) logo.material.map.dispose();
      logo.material.dispose();
    }
  }
}

// Watch for playing state changes
watch(
  () => props.isPlaying,
  (newValue) => {
    if (!newValue) {
      // Reset to rest state when not playing
      bassData.value = 0;
      midData.value = 0;
      trebleData.value = 0;
    }
  },
);

onMounted(() => {
  initThreeJS();
});

onUnmounted(() => {
  cleanup();
});
</script>

<style scoped>
canvas {
  display: block;
}
</style>
