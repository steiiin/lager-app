<script setup>

/**
 * Welcome - Page component
 *
 * The root page.
 *
 */

// #region Imports

  // Vue composables
  import { ref, onMounted, onUnmounted, computed } from 'vue'
  import { Head, router } from '@inertiajs/vue3'

  // Local composables
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcLockDialog from '@/Dialogs/LcLockDialog.vue'
  import LcUnlockDialog from '@/Dialogs/LcUnlockDialog.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
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
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
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

// #region Lock/Unlock-Logic

  // DialogProps
  const unlockDialog = ref(null)
  const lockDialog = ref(null)

  // Methods
  function unlockUi() {
    unlockDialog.value.open()
  }
  function lockUi() {
    lockDialog.value.open()
  }

  // #region TemplateProps

    const pageClasses = computed(() => {
      return 'page-welcome' + (props.isUnlocked
        ? ''
        : 'page-welcome--locked')
    })

  // #endregion

// #endregion
// #region Shortcuts

  const openKioskSettings = () => {
    if (typeof OpenKiosk != 'undefined') {
      OpenKiosk.settings()
    } else {
      console.warn('Lager-App: Not in OpenKiosk')
    }
  }

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerK1(openBookOut)
    InputService.registerK2(openWhereIs)
    InputService.registerK3(openBookIn)
    InputService.registerKKiosk(openKioskSettings)
    inventoryStore.fetchStore()
  })
  onUnmounted(() => {
    InputService.unregisterK1(openBookOut)
    InputService.unregisterK2(openWhereIs)
    InputService.unregisterK3(openBookIn)
    InputService.unregisterKKiosk(openKioskSettings)
  })

// #endregion

</script>
<template>

  <Head title="Home" />
  <IdleCursor />

  <div :class="pageClasses">

    <LcButton class="page-welcome__BookOut"
      type="primary" icon="mdi-barcode-scan"
      @click="openBookOut">Verbrauch<kbd v-if="!isTouchMode">1</kbd>
    </LcButton>

    <LcButton class="page-welcome__WhereIs"
      type="primary" icon="mdi-home-search-outline"
      @click="openWhereIs">Wo ist ... ?<kbd v-if="!isTouchMode">2</kbd>
    </LcButton>

    <LcButton class="page-welcome__BookIn"
      icon="mdi-basket-outline"
      @click="openBookIn">Lieferung<kbd v-if="!isTouchMode">3</kbd>
    </LcButton>

    <LcButton class="page-welcome__Login" v-if="isUnlocked"
      icon="mdi-lock-outline"
      @click="lockUi">
    </LcButton>
    <LcButton class="page-welcome__Login" v-else
      icon="mdi-lock-open-variant-outline"
      @click="unlockUi">
    </LcButton>

    <LcButton class="page-welcome__Inventur" v-if="isUnlocked"
      @click="openInventory">Inventur
    </LcButton>

  </div>
  <div class="page-welcome__invisible-usagescanner">
    <LcUsageInput :is-unlocked="isUnlocked"
      @select-usage="openBookOutWithUsage">
    </LcUsageInput>
  </div>

  <!-- Dialogs -->
  <LcUnlockDialog ref="unlockDialog" />
  <LcLockDialog ref="lockDialog" />
  <LcRouteOverlay v-show="isRouting" />

</template>
<style lang="scss" scoped>
.page-welcome {

  width: 100%;
  height: 100%;
  display: grid;
  padding: 10vh 10vw;
  background: var(--main-light);
  color: var(--main-dark);
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

  &__WhereIs {
    grid-area: WhereIs;
  }

  &__BookIn {
    grid-area: BookIn;
  }

  &__BookOut {
    grid-area: BookOut;
  }

  &__Login {
    grid-area: Login;
  }

  &__Inventur {
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

  &__invisible-usagescanner {
    display: none;
  }

}
</style>
