<template>
  <div id="wp-agent-app" class="wp-agent-container" v-if="wpAgentData.showModal">
    <ChatModal v-if="isModalOpen" @close="toggleModal" />
    <button id="open-chat-modal"
            @click="toggleModal"
            class="wp-agent-button tw-fixed tw-bottom-6 tw-right-6 tw-bg-white tw-rounded-full tw-w-16 tw-h-16 tw-flex tw-items-center tw-justify-center tw-text-6xl tw-shadow-lg">
      <img :src="wpAgentData.logoUrl" alt="WP Agent logo" class="tw-w-10 tw-h-10" />
      <span class="tw-sr-only">Open chat</span>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import ChatModal from './components/ChatModal.vue';

const wpAgentData = ref(window.wpAgentData);
const isModalOpen = ref(false);

onMounted(() => {
  const storedValue = localStorage.getItem('isModalOpen');
  isModalOpen.value = storedValue === 'true' && wpAgentData.value.showModal;
});

const toggleModal = () => {
  isModalOpen.value = !isModalOpen.value;
  localStorage.setItem('isModalOpen', isModalOpen.value.toString());
};
</script>

<style scoped>
.wp-agent-container {
  all: initial;
}

.wp-agent-button {
  z-index: 99999;
}
</style>
