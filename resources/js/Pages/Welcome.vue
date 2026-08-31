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
  import LcUnlockInventory from '@/Dialogs/LcUnlockInventory.vue'
  import LcRouteOverlay from '@/Components/LcRouteOverlay.vue'
  import LcUsageInput from '@/Components/LcUsageInput.vue'
  import LcFeedback from '@/Components/LcFeedback.vue'
  import IdleCursor from '@/Components/IdleCursor.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    isTouchMode: {
      type: Boolean,
      required: true,
    },
    newsfeed: {
      type: Array,
      required: true,
    },
  })

  const hasNews = computed(() => props.newsfeed.length > 0)

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openBookOut() {
    router.get('/bookout')
  }
  function openBookOutWithUsage(usage) {
    router.get(route('bookout.index', { usageId: usage.id }));
  }
  function openBookIn() {
    router.get('/bookin')
  }

  const handleIdle = () => {
    router.reload()
  }

// #endregion

// #region Lock/Unlock-Logic

  // DialogProps
  const unlockDialog = ref(null)

  // Methods
  function unlockInventory() {
    unlockDialog.value.open()
  }

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
// #region Feedback

  const feedback = ref(null)

  const warnAboutUsage = () => {
    feedback.value.usageError()
  }

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerK1(openBookOut)
    InputService.registerK2(openBookIn)
    InputService.registerKKiosk(openKioskSettings)
    InputService.registerIdle(handleIdle)
    inventoryStore.fetchStore()
  })
  onUnmounted(() => {
    InputService.unregisterK1(openBookOut)
    InputService.unregisterK2(openBookIn)
    InputService.unregisterKKiosk(openKioskSettings)
    InputService.unregisterIdle(handleIdle)
  })

// #endregion

</script>
<template>

  <Head title="Home" />
  <IdleCursor />

  <div class="page-welcome" :class="{ 'with-news': hasNews }">

    <LcButton class="page-welcome__BookOut"
      type="primary" icon="mdi-barcode-scan"
      @click="openBookOut">Verbrauch<kbd v-if="!isTouchMode">1</kbd>
    </LcButton>

    <LcButton class="page-welcome__BookIn"
      type="primary" icon="mdi-basket-outline"
      @click="openBookIn">Lieferung<kbd v-if="!isTouchMode">2</kbd>
    </LcButton>

    <LcButton class="page-welcome__Inventory"
      icon="mdi-lock-open-variant-outline"
      @click="unlockInventory">
    </LcButton>

    <div v-if="hasNews" class="page-welcome__News">
      <h1>Infos</h1>
      <article v-for="news in newsfeed" :key="news.id" class="page-welcome__News-item">
        <h2>{{ news.title }}</h2>
        <p>{{ news.message }}</p>
      </article>
    </div>

  </div>
  <div class="page-welcome__invisible-usagescanner">
    <LcUsageInput
      @select-usage="openBookOutWithUsage"
      @other-code="warnAboutUsage">
    </LcUsageInput>
  </div>

  <!-- Dialogs -->
  <LcFeedback ref="feedback" />
  <LcUnlockInventory ref="unlockDialog" />
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
  grid-template-columns: 0.5fr 0.5fr;
  grid-template-rows: 1.0fr 1.0fr 0.5fr;
  gap: 1rem;
  grid-template-areas:
    "BookOut BookIn"
    "BookOut BookIn"
    "BookOut Inventory";

  &__BookIn {
    grid-area: BookIn;
  }

  &__BookOut {
    grid-area: BookOut;
  }

  &__Inventory {
    grid-area: Inventory;
  }

  &__News {
    grid-area: News;
    min-height: 0;
    padding: 1.5rem;
    overflow-y: auto;
    border: .5rem solid var(--main-dark);

    & > h1 {
      margin: -1.5rem -1.5rem 1rem -1.5rem;
      padding: 1.5rem;
      font-size: 1.5rem;
      text-transform: uppercase;
      background: var(--main-dark);
      color: var(--main-light);
    }

    &-item {

      border-bottom: .2rem solid var(--main-dark);
      padding: 1rem 0;

      &:last-child {
        border-bottom: none;
      }

      & h2 {
        font-size: 1.15rem;
      }

      & p {
        white-space: pre-line;
        overflow-wrap: anywhere;
      }
    }

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

.page-welcome.with-news {

  grid-template-columns: 0.3fr 0.3fr 0.3fr 0.5fr;
  grid-template-rows: 1.0fr 1.0fr 0.5fr;
  gap: 1rem;
  grid-template-areas:
    "BookOut BookOut BookOut News"
    "BookOut BookOut BookOut News"
    "BookIn BookIn Inventory News";

}

</style>
