<script setup>

// #region imports

  // Vue composables
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcLockDialog from '@/Dialogs/LcLockDialog.vue'
  import LcUnlockDialog from '@/Dialogs/LcUnlockDialog.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import InputService from '@/Services/InputService'

// #endregion

// #region props

  defineProps({
    usages: {
      type: Array,
      required: true,
    },
    isUnlocked: {
      type: Boolean,
      required: true,
    },
    isTouchMode: {
      type: Boolean,
      required: true,
    }
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  function openWhereIs() {
    router.get('/whereis')
  }
  function openBookOut() {
    router.get('/bookout')
  }
  function openBookOutWithUsage(usage) {
    router.get(route('bookout.index', { usageId: usage.id }));
  }
  function openBookIn() {
    router.get('/bookin')
  }
  function openInventory() {
    router.get('/inventory')
  }

// #endregion

// #region locking/unlocking

  // ref
  const unlockDialog = ref(null)
  const lockDialog = ref(null)

  // methods
  function unlockUi() {
    unlockDialog.value.open()
  }
  function lockUi() {
    lockDialog.value.open()
  }

// #endregion

// #region touchmode

  const openKioskSettings = () => {
    if (typeof OpenKiosk != 'undefined') {
      OpenKiosk.settings()
    } else {
      console.warn('Lager-App: Not in OpenKiosk')
    }
  }

  onMounted(() => {
    InputService.registerK1(openBookOut)
    InputService.registerK2(openWhereIs)
    InputService.registerK3(openBookIn)
    InputService.registerKl(openKioskSettings)
  })
  onUnmounted(() => {
    InputService.unregisterK1(openBookOut)
    InputService.unregisterK2(openWhereIs)
    InputService.unregisterK3(openBookIn)
    InputService.unregisterKl(openKioskSettings)
  })

// #endregion

</script>
<template>

  <Head title="Home" />

  <div class="app-Welcome" :class="{ 'app-Welcome--locked': !isUnlocked }">

    <lc-button class="app-Welcome--BookOut" 
      type="primary" icon="mdi-barcode-scan"
      @click="openBookOut">Verbrauch<kbd v-if="!isTouchMode">1</kbd>
    </lc-button>

    <lc-button class="app-Welcome--WhereIs"
      type="primary" icon="mdi-home-search-outline"
      @click="openWhereIs">Wo ist ... ?<kbd v-if="!isTouchMode">2</kbd>
    </lc-button>

    <lc-button class="app-Welcome--BookIn"
      icon="mdi-basket-outline"
      @click="openBookIn">Lieferung<kbd v-if="!isTouchMode">3</kbd>
    </lc-button>

    <lc-button class="app-Welcome--Login" v-if="isUnlocked"
      icon="mdi-lock-outline"
      @click="lockUi">
    </lc-button>
    <lc-button class="app-Welcome--Login" v-else
      icon="mdi-lock-open-variant-outline"
      @click="unlockUi">
    </lc-button>

    <lc-button class="app-Welcome--Inventur" v-if="isUnlocked" 
      @click="openInventory">Inventur
    </lc-button>

  </div>
  <div class="app-Welcome--invisibleUsageScanner">
    <LcUsageInput
      :usages="usages" :is-unlocked="isUnlocked"
      @select-usage="openBookOutWithUsage">
    </LcUsageInput>
  </div>
  
  <LcUnlockDialog ref="unlockDialog" />
  <LcLockDialog ref="lockDialog" />
  <LcRouteOverlay v-show="isRouting" />

</template>
<style lang="scss" scoped>
.app-Welcome {
  width: 100%;
  height: 100%;
  display: grid;
  padding: 10vh 10vw;
  background: var(--lc-main-background);
  color: var(--lc-main-text);
  grid-template-columns: repeat(6, 0.5fr);
  grid-template-rows: 1.5fr 0.5fr 0.5fr;
  gap: 1rem;
  grid-template-areas:
    "BookOut BookOut BookOut WhereIs WhereIs WhereIs"
    "BookOut BookOut BookOut BookIn BookIn Login"
    "Inventur Inventur Inventur Inventur Inventur Inventur";

  &--locked {
    grid-template-rows: 1.5fr 0.5fr;
    grid-template-areas:
      "BookOut BookOut BookOut WhereIs WhereIs WhereIs"
      "BookOut BookOut BookOut BookIn BookIn Login";
  }

  &--WhereIs {
    grid-area: WhereIs;
  }

  &--BookIn {
    grid-area: BookIn;
  }

  &--BookOut {
    grid-area: BookOut;
  }

  &--Login {
    grid-area: Login;
  }

  &--Inventur {
    grid-area: Inventur;
  }

  & :deep(.lc-button) {
    font-size: 2rem;
    letter-spacing: 0;
  }

  & :deep(.lc-button.lc-smaller) {
    font-size: 1.5rem;
  }

  & :deep(.material-design-icon__svg) {
    width: 3rem;
    height: 3rem;
  }

  &--invisibleUsageScanner {
    display: none;
  }

}
</style>
