<script setup>

/**
 * LcUnlockDialog - Dialog component
 *
 * A dialog for unlocking the app.
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref, nextTick } from 'vue'
  import { router } from '@inertiajs/vue3'

// #endregion

// #region Dialog-Logic

  // DialogProps
  const isVisible = ref(false)

  // DialogMethods
  const open = async () => {
    isUnlockFailed.value = false
    isUnlocking.value = false
    password.value = ''
    isVisible.value = true
    await nextTick()
    document.getElementById('lc-unlockdialog-passwordbox')?.focus()
  }
  const cancel = () => {
    isVisible.value = false
  }

  function openInventory() {
    router.get('/inventory')
  }

// #endregion
// #region Unlock-Logic

  const password = ref('')

  const doUnlock = () => {
    if (!isValidPassword) { return }

    isUnlockFailed.value = false
    isUnlocking.value = true

    router.post('/', { action: 'UNLOCK', password: password.value }, {

      onSuccess: (page) => {
        if (page.props.isUnlocked) {

          openInventory()
          cancel()

        } else {
          isUnlockFailed.value = true
          isUnlocking.value = false
        }
      },
      onError: () => {
        isUnlockFailed.value = true
        isUnlocking.value = false
      },

    })

  }

  // #region TemplateProps

    const isValidPassword = computed(() => password.value.trim().length > 0)

    const isUnlockFailed = ref(false)
    const isUnlocking = ref(false)

  // #endregion

// #endregion

// #region Expose

  defineExpose({ open })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="450px" :persistent="isUnlocking" @after-leave="cancel">
    <v-card prepend-icon="mdi-lock" class="rounded-0" title="Lagerverwaltung entsperren">
      <v-divider></v-divider>
      <v-card-text>
        <p class="mb-4">Bitte gib das Passwort ein, um die Lagerverwaltung zu entsperren.</p>

        <v-text-field v-model="password" id="lc-unlockdialog-passwordbox"
          label="Passwort" :disabled="isUnlocking"
          type="password" variant="solo"
          prepend-icon="mdi-lock" hide-details="auto"
          @keydown.enter="doUnlock">
        </v-text-field>

        <v-alert class="mt-4" v-if="isUnlockFailed"
          text="Das Passwort war falsch." type="error">
        </v-alert>

      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="mx-4 mb-2">
        <v-btn :disabled="isUnlocking"
          @click="cancel">Abbrechen
        </v-btn>
        <v-btn :loading="isUnlocking" :disabled="!isValidPassword"
          color="primary" variant="tonal"
          @click="doUnlock">OK
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
