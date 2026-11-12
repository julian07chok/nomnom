<script setup>
import { nextTick, onBeforeUnmount, ref, useId } from "vue";
import AppButton from "./AppButton.vue";

const dialogElement = ref(null);
const titleId = useId();
const messageId = useId();

const defaults = {
  title: "Are you sure?",
  message: "",
  confirmText: "Confirm",
  cancelText: "Cancel",
  variant: "primary",
  showCancel: true,
};

const settings = ref({ ...defaults });

let resolvePending = null;

function finish(result) {
  const resolve = resolvePending;
  resolvePending = null;
  resolve?.(result);
}

async function open(options = {}) {
  if (resolvePending) return false;

  settings.value = { ...defaults, ...options };

  const result = new Promise((resolve) => {
    resolvePending = resolve;
  });

  await nextTick();

  if (!dialogElement.value?.isConnected) {
    finish(null);
    return result;
  }

  try {
    dialogElement.value.returnValue = "";
    dialogElement.value.showModal();
  } catch {
    finish(false);
  }

  return result;
}

function closeDialog(confirmed) {
  if (dialogElement.value?.open) {
    dialogElement.value.close(confirmed ? "confirm" : "cancel");
  }
}

function onClose() {
  finish(dialogElement.value?.returnValue === "confirm");
}

onBeforeUnmount(() => {
  if (dialogElement.value?.open) {
    dialogElement.value.close("cancel");
  }

  finish(null);
});

defineExpose({ open });
</script>

<template>
  <dialog
    ref="dialogElement"
    class="confirm-dialog"
    :aria-labelledby="titleId"
    :aria-describedby="messageId"
    @cancel.prevent="closeDialog(false)"
    @close="onClose"
  >
    <h2 :id="titleId" class="confirm-dialog__title">
      {{ settings.title }}
    </h2>

    <p :id="messageId" class="confirm-dialog__message">
      {{ settings.message }}
    </p>

    <div class="confirm-dialog__actions">
      <AppButton
        v-if="settings.showCancel"
        variant="secondary"
        autofocus
        @click="closeDialog(false)"
      >
        {{ settings.cancelText }}
      </AppButton>

      <AppButton
        :variant="settings.variant"
        :autofocus="!settings.showCancel"
        @click="closeDialog(true)"
      >
        {{ settings.confirmText }}
      </AppButton>
    </div>
  </dialog>
</template>

<style scoped src="../../assets/css/confirm-dialog.css"></style>
