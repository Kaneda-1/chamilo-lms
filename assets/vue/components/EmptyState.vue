<template>
  <div class="empty-state">
    <div class="empty-state__container">
      <span
        :class="chamiloIconToClass[icon]"
        aria-hidden="true"
        class="empty-state__icon"
      />
      <p
        class="empty-state__summary"
        v-text="summary"
      />
      <p
        class="empty-state__detail"
        v-text="detail"
      />
      <slot />
    </div>
  </div>
</template>

<script setup>
import { chamiloIconToClass } from "./basecomponents/ChamiloIcons"
import { iconValidator } from "./basecomponents/validators"

defineProps({
  summary: {
    type: String,
    default: "",
    required: true,
  },
  detail: {
    type: String,
    default: "",
    required: false,
  },
  icon: {
    type: String,
    default: "",
    validator: (value) => {
      if (value === "") {
        return true
      }
      return iconValidator(value)
    },
  },
})
</script>
