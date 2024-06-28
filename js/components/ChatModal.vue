<template>
  <div class="tw-bg-gray-50 tw-fixed tw-bottom-24 tw-right-6 tw-rounded-xl tw-shadow-lg tw-z-50 tw-w-[28rem] tw-overflow-hidden">
    <div class="tw-px-4 tw-py-3 tw-bg-primary-100/50 tw-drop-shadow-lg tw-flex tw-justify-between">
      <div class="tw-font-bold tw-text-xs tw-text-gray-900">WP Agent</div>
      <button @click="newConvo" class="tw-text-xs tw-text-gray-500">
        New Chat
      </button>
    </div>
    <ul ref="chatContainer" class="tw-px-4 tw-pt-4 tw-overflow-auto tw-space-y-2" :style="{ maxHeight: 'calc(100vh - 17rem)' }">
      <li v-for="(item, index) in conversation.messages" :key="index" class="">
        <div v-if="item.role === 'user'" class="tw-flex tw-flex-col tw-items-end">
          <p class="tw-bg-primary-500 tw-text-white tw-rounded-xl tw-px-3 tw-py-2 tw-max-w-[80%]">{{ item.content }}</p>
        </div>
        <div v-else class="tw-flex tw-flex-col tw-items-start">
          <div class="tw-bg-gray-100 tw-rounded-xl tw-px-3 tw-py-2 tw-max-w-[80%]">
            <div class="tw-prose tw-prose-sm" v-html="marked(item.content)"></div>
          </div>
        </div>
      </li>
      <li v-if="isTyping" class="tw-flex tw-flex-col tw-items-start">
        <div class="tw-flex tw-space-x-1 tw-mt-2">
          <span class="dot tw-inline-block tw-w-2 tw-h-2 tw-rounded-full tw-bg-gray-400"></span>
          <span class="dot tw-inline-block tw-w-2 tw-h-2 tw-rounded-full tw-bg-gray-400"></span>
          <span class="dot tw-inline-block tw-w-2 tw-h-2 tw-rounded-full tw-bg-gray-400"></span>
        </div>
      </li>
    </ul>
    <div class="tw-p-4 tw-flex tw-items-start tw-space-x-4">
      <div class="tw-min-w-0 tw-flex-1">
        <form @submit.prevent="submit" class="tw-relative">
          <div
            class="tw-overflow-hidden tw-rounded-lg tw-shadow-sm tw-ring-1 tw-ring-inset tw-ring-gray-300 focus-within:tw-ring-2 focus-within:tw-ring-primary-600">
            <label for="comment" class="tw-sr-only">Ask WP Agent</label>
            <textarea v-model="message" rows="2" name="comment" id="comment"
              class="tw-block tw-w-full tw-resize-none tw-border-0 tw-bg-transparent tw-py-1.5 tw-text-gray-900 placeholder:tw-text-gray-400 focus:tw-ring-0 sm:tw-text-sm sm:tw-leading-6"
              placeholder="Ask WP Agent..." @keydown="handleKeydown"></textarea>
          </div>
          <button type="submit"
            class="tw-inline-flex tw-absolute tw-bottom-1 tw-right-1 tw-items-center tw-rounded-md tw-bg-primary-500 tw-px-3 tw-py-2 tw-text-sm tw-font-semibold tw-text-white tw-shadow-sm hover:tw-bg-primary-600 focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-offset-2 focus-visible:tw-outline-primary-500">Send</button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import { ulid } from 'ulid';
import { marked } from 'marked';

const message = ref('');
const conversation = ref({ messages: [] });
const isTyping = ref(false);
const chatContainer = ref(null);

const wpAgentData = ref(window.wpAgentData);

const conversationId = ref(localStorage.getItem('conversationId'));

const generateConversationId = () => {
  conversationId.value = ulid();
  localStorage.setItem('conversationId', conversationId.value);
};

if (!conversationId.value) {
  generateConversationId();
}

onMounted(() => {
  fetchConversation();
});

const newConvo = () => {
  generateConversationId();
  conversation.value = { messages: [] };
  fetchConversation();
};

const scrollToBottom = () => {
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  }
};

watch(() => conversation.value.messages, () => {
  nextTick(scrollToBottom);
}, { deep: true });

const fetchConversation = async () => {
  try {
    const response = await axios.get(`${wpAgentData.value.apiEndpoint}/conversations/${conversationId.value}`, {
      params: { wp_user_id: wpAgentData.value.wpUserId },
      headers: {
        'Authorization': `Bearer ${wpAgentData.value.apiKey}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    conversation.value = response.data.data;
    nextTick(scrollToBottom);
  } catch (error) {
    console.error('API Error:', error);
  }
};

const submit = async () => {
  if (!message.value.trim()) return;

  const content = message.value;

  conversation.value.messages.push({
    role: 'user',
    content: content
  });

  message.value = '';
  isTyping.value = true;

  try {
    const response = await axios.post(`${wpAgentData.value.apiEndpoint}/conversations/${conversationId.value}`, {
      content: content,
      user_id: wpAgentData.value.wpUserId
    }, {
      headers: {
        'Authorization': `Bearer ${wpAgentData.value.apiKey}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    console.log('API Response:', response.data);
    if (response.data && response.data.data) {
      conversation.value.messages.push(response.data.data);
    }
  } catch (error) {
    console.error('API Error:', error);
  } finally {
    isTyping.value = false;
  }
};

const handleKeydown = (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    submit();
  }
};
</script>

<style scoped>
.dot {
  animation: wave 1s linear infinite;
}

.dot:nth-child(2) {
  animation-delay: -0.9s;
}

.dot:nth-child(3) {
  animation-delay: -0.8s;
}

@keyframes wave {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-5px);
  }
}
</style>
