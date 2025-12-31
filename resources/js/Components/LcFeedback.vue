<script setup>

/**
 * LcFeedback - Component
 *
 * An indicator to give the user some feedback for scanning.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed } from 'vue'

  // 3rd party composables
  import { useSound } from '@vueuse/sound'

// #endregion
// #region Props

  const feedbackVisible = ref(false)
  const feedbackType = ref('info') // 'info' | 'alert'
  const feedbackTitle = ref('')
  const feedbackMessage = ref('')

  const showTimeout = ref(null)

  const show = (type, title, message) => {

    feedbackType.value = type
    feedbackTitle.value = title
    feedbackMessage.value = message
    feedbackVisible.value = true
    playSoundMatched()

    if (showTimeout.value) { clearTimeout(showTimeout.value) }
    showTimeout.value = setTimeout(hide, feedbackTimeoutMatched.value)

  }

  const hide = () => {
    feedbackVisible.value = false
  }

  // #region TemplateProps

    const feedbackTypeMatched = computed(() => {
      if (['info','success','error'].includes(feedbackType.value)) { return feedbackType.value }
      return ''
    })

    const feedbackTimeoutMatched = computed(() => {
      if (feedbackType.value == 'error') { return 4000 }
      return 1000
    })

  // #endregion
  // #region Sounds

    import errorTone from '@/assets/sounds/battery-caution.oga'
    const errorToneSound = useSound(errorTone)

    import successTone from '@/assets/sounds/desktop-login.oga'
    const successToneSound = useSound(successTone)

    const playSoundMatched = () => {
      if (feedbackType.value == 'error') {
        errorToneSound.play()
      }
      else if (feedbackType.value == 'success') {
        successToneSound.play()
      }
    }

  // #endregion

// #endregion

const info = (title, message) => {
  show('info', title, message)
}
const error = (title, message) => {
  show('error', title, message)
}
const success = (title, message) => {
  show('success', title, message)
}

const usageError = () => {
  error('Verwendung vergessen!', 'Bevor du mit dem Austragen aus dem Lager beginnen kannst, musst du ein Fahrzeug wählen oder scannen.')
}

const scanSuccess = (item) => {
  success(item.name, 'wurde hinzugefügt.')
}

const bookSuccess = () => {
  success('Entnahme verbucht', 'Danke, für deinen Einkauf.')
}

defineExpose({ info, error, success, usageError, scanSuccess, bookSuccess })

</script>
<template>
  <v-dialog class="lc-feedback" v-model="feedbackVisible" max-width="450px">
    <v-alert
      :text="feedbackMessage"
      :title="feedbackTitle"
      :type="feedbackTypeMatched"
    ></v-alert>
  </v-dialog>
</template>
<style lang="scss" scoped>
.lc-scanindicator {

  position: relative;
  width: 72px;
  height: 72px;

  & > * {
    position: absolute;
  }

  & .v-icon {
    left: 19px;
    top: 19px;
  }


}
</style>
