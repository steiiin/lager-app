<script setup>

/**
 * LcItemInput - Component
 *
 * A picker component for scanning/searching items.
 *
 * Props:
 *  - allowScan (Boolean): Enables the scan-mode.
 *  - adminMode (Boolean): Show additional info per item and enables new-item button.
 *  - disabled (Boolean): Disables the component for scanning/searching.
 *  - resultSpecs (Object): Set the width (w) and indent (i) of the absolute placed result panel.
 *  - cart (String): The current booking table, to show booking info in results.
 *
 * Emits:
 *  - createNew: Emitted when the user wants a new item.
 *  - selectItem: Emitted when the user scanned an item.
 *  - ctrlFinish: Emitted when the user scanned the finish-code.
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref, nextTick, onMounted, onUnmounted, watch } from 'vue'

  // 3rd-party composables
  import { debounce } from 'lodash'

  // Local composables
  import InputService from '@/Services/InputService'
  import { useInventoryStore } from '@/Services/StoreService'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()
  const props = defineProps({
    allowScan: {
      type: Boolean,
      default: true,
    },
    adminMode: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    resultSpecs: {
      type: Object,
      default: { w: 850, i: 11 },
    },
    cart: {
      type: Array,
      required: false,
      default: [],
    },
  })

  // #region TemplateProps

    const hasAnyItems = computed(() => inventoryStore.items.length > 0)

    const pickerDescriptionTitle = computed(() =>
      props.disabled ? '' : (hasAnyItems.value ? 'Scanne oder suche dein Material ...' : 'Kein Material angelegt')
    )

    const pickerSearchBoxClasses = computed(() => {
      return 'lc-picker__search' +
        (props.allowScan ? '' : ' lc-picker__search-noscan')
    })

    const pickerResultWidth = computed(() => (props.resultSpecs?.w ?? 850) + 'px')
    const pickerResultTop = computed(() => (props.resultSpecs?.i ?? 11) + 'rem')
    const pickerResultCSS = computed(() => `height:calc(100% - 0.5rem - ${pickerResultTop.value}); top: ${pickerResultTop.value}; width: ${pickerResultWidth.value};`)



  // #endregion

// #endregion
// #region Emits

  const emit = defineEmits([
    'createNew',
    'selectItem',
    'ctrlFinish',
  ])

  const selectItemBySearch = (item) => {
    emit('selectItem', item, null)
    changeModeToScan()
  }
  const selectItemByScan = (item, amount) => {
    emit('selectItem', item, amount)
  }
  const createNew = () => {
    emit('createNew')
    changeModeToScan()
  }

// #endregion

// #region Input-Logic

  // #region ItemMode

    // Props
    const currentMode = ref(props.allowScan ? 'SCAN' : 'TEXT')

    // TemplateProps
    const inScanMode = computed(() => currentMode.value === 'SCAN')
    const inTextMode = computed(() => currentMode.value === 'TEXT')

    // Methods
    const changeModeToScan = () => {
      searchText.value = ''
      if (!props.allowScan) { return }
      currentMode.value = 'SCAN'
    }
    const changeModeToText = async (text = '') => {
      searchText.value = text
      currentMode.value = 'TEXT'
      await nextTick()
      document.getElementById('id-picker-searchbox')?.focus()
    }

  // #endregion
  // #region ItemMode: SCAN

    const findItem = (code) => {

      // change to scanmode if code in textmode received
      if (!inScanMode.value) {
        changeModeToScan()
      }

      // emit ctrl-codes
      if (code === 'LC-2000001000') {
        emit('ctrlFinish')
        return
      }

      // search item
      const found = inventoryStore.findItemByBarcode(code)
      if (!found) { return }
      selectItemByScan(found.item, found.amount)

    }

  // #endregion
  // #region ItemMode: TEXT

    // Textbox-Props
    const searchText = ref('')
    const isTyping = ref(true)

    // Debounce Textbox
    const debouncedSearchText = ref('')
    const updateSearchText = debounce((value) => {
      debouncedSearchText.value = value
      isTyping.value = false
    }, 300)
    watch(searchText, (newValue) => {
      isTyping.value = true
      updateSearchText(newValue)
    })

    // #region Search-Logic

      // Map
      const bookingsMap = computed(() => {
        const map = new Map()
        props.cart.forEach(booking => {
          map.set(booking.item_id, booking)
        })
        return map
      })

      // Props
      const hasTyped = computed(() => searchText.value.trim().length > 0)

      // Filter-Prop
      const filteredItems = computed(() => {

        // search text
        const lcSearchText = debouncedSearchText.value.toLowerCase()
        if (lcSearchText.trim().length === 0) { return [] }

        const results = []
        const bookings = bookingsMap.value
        const items = inventoryStore.searchableItems

        for (const item of items) {

          let relevance = 0

          if (item.pp_name === lcSearchText) {
            relevance += 50
          }
          else if (item.pp_name.startsWith(lcSearchText)) {
            relevance += 30
          }

          if (item.pp_search_altnames_list?.includes(lcSearchText)) {
            relevance += 20
          }
          else if (item.pp_search_altnames_list?.some(name => name.startsWith(lcSearchText))) {
            relevance += 15
          }

          if (lcSearchText.length > 1) {

            if (relevance <= 30 && item.pp_name.includes(lcSearchText)) {
              relevance += 10
            }

            if (item.pp_search_altnames?.includes(lcSearchText)) {
              relevance += 5
            }

            if (item.pp_search_tags_list?.some(tag => tag.startsWith(lcSearchText))) {
              relevance += 2
            }

          }

          if (relevance > 1) {

            const quantityColor = props.adminMode ? getQuantityColor(item) : null
            const expiryColor = props.adminMode ? getExpiryColor(item) : null
            const expiryText = props.adminMode ? getExpiryText(item) : null

            const bookingEntry = bookings.get(item.id) || null
            const isOnBooking = bookingEntry !== null
            const bookingText = isOnBooking
              ? bookingEntry.item_amount + ' ' + item.basesize.unit
              : null;

            results.push({
              ...item,
              relevance,
              hasAltNames: !!item.pp_search_altnames_list,
              hasTags: !!item.pp_search_tags_list,
              quantityColor,
              expiryColor,
              expiryText,
              isOnBooking,
              bookingText,
            })

          }

        }

        // sort if necessary
        if (results.length > 1) {
          results.sort((a, b) => b.relevance - a.relevance)
        }

        // slice over 3
        if (results.length > 3) {
          const firstScore = results[0].relevance
          let cutAfter = 4
          while ((results.length > cutAfter) && (cutAfter < 10) && results[cutAfter - 1].relevance === firstScore) {
            cutAfter += 1
          }
          results.splice(cutAfter-1)
        }

        return results

      })

      // TemplateProps
      const hasAnyResults = computed(() => filteredItems.value.length > 0)
      const hasExactlyOneResult = computed(() => filteredItems.value.length === 1)

      // Methods
      const getFirstResult = () => (filteredItems.value[0] ?? null)
      const selectFirstResult = () => {
        if (hasExactlyOneResult.value) {
          // select only found item
          selectItemBySearch(getFirstResult())
        }
        if (searchText.value.startsWith('LC-')) {
          // scanned something while in text-mode
          searchText.value = ''
        }
      }

    // #endregion
    // #region Keyboard-Input

      const receiveKeys = async (keys) => {

        // change to textmode if in scanmode
        if (inScanMode.value) { changeModeToText() }

        // focus searchbox if it is not active element
        if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
          searchText.value += keys
          currentMode.value = 'TEXT'
          await nextTick()
          document.getElementById('id-picker-searchbox')?.focus()
        }

      }
      const receiveBackspace = async () => {

        if (inScanMode.value) { return }
        if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
          await nextTick()
          document.getElementById('id-picker-searchbox')?.focus()
        }

      }

      const handleEscape = () => {

        if (inTextMode.value) {
          changeModeToScan()
          return false
        }
        return true

      }

    // #endregion

    // #region ItemProps-Getter (for pickerresult)

      const getQuantityColor = (item) => {
        if (item.demanded_quantity <= item.min_stock) {
          return 'error'
        }
        else if (item.demanded_quantity <= (item.min_stock + item.max_stock/2)) {
          return 'warning'
        }
        else {
          return 'success'
        }
      }

      const getExpiryColor = (item) => {
        const daysDiff = Math.round(
          (new Date(item.current_expiry).getTime() - new Date().getTime()) /
          (1000 * 60 * 60 * 24))
        if (daysDiff > 21) {
          return 'success'
        }
        else if (daysDiff > 14) {
          return 'warning'
        }
        else {
          return 'error'
        }
      }

      const getExpiryText = (item) => {
        if (!item.current_expiry) { return "n/v" }
        return new Date(item.current_expiry).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }).replace(' ', '-').replace('.', '');
      }

    // #endregion

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {

    // init mode
    if (props.allowScan) {
      changeModeToScan()
    } else {
      changeModeToText()
    }

    // register inputs
    InputService.registerScan(findItem)
    InputService.registerKeys(receiveKeys)
    InputService.registerBackspace(receiveBackspace)

  })

  onUnmounted(() => {
    InputService.unregisterScan(findItem)
    InputService.unregisterKeys(receiveKeys)
    InputService.unregisterBackspace(receiveBackspace)
  })

// #endregion
// #region Expose

  defineExpose({ handleEscape })

// #endregion

</script>
<template>

  <section class="lc-picker" v-if="inScanMode">

    <div class="lc-picker__scanner">
      <LcScanIndicator
        :active="hasAnyItems && !disabled">
      </LcScanIndicator>
    </div>
    <div class="lc-picker__description">
      <div class="lc-picker__description-title">
        {{ pickerDescriptionTitle }}
      </div>
    </div>

    <template v-if="!disabled">

      <LcButton v-if="hasAnyItems"
        class="lc-picker__action" icon="mdi-form-textbox"
        @click="changeModeToText()">Suchen
      </LcButton>
      <LcButton v-if="adminMode"
        class="lc-picker__action" icon="mdi-plus"
        @click="createNew">Neu
      </LcButton>

    </template>

  </section>
  <section class="lc-picker" v-else-if="inTextMode">

    <LcButton v-if="allowScan"
      class="lc-picker__action" icon="mdi-arrow-left"
      @click="changeModeToScan">Zurück
    </LcButton>

    <div :class="pickerSearchBoxClasses">
      <v-text-field id="id-picker-searchbox" v-model="searchText"
        label="Suche nach Material ..." variant="outlined" hide-details :rounded="0"
        @keyup.enter="selectFirstResult">
      </v-text-field>
    </div>

  </section>
  <section class="lc-picker__result" v-if="hasAnyItems && hasTyped" :style="pickerResultCSS">

    <v-empty-state v-show="!hasAnyResults && !isTyping"
      :title="isTyping ? '' : 'Kein Material gefunden'"
      :text="isTyping ? '' : 'Versuche einen anderen Suchbegriff'"
    ></v-empty-state>

    <v-card v-for="item in filteredItems" :key="item.id"
      class="lc-picker__result--item" link variant="flat"
      @click="selectItemBySearch(item)">
      <div class="lc-picker__result--item-head">
        <div class="lc-picker__result--item-head-name">{{ item.name }}</div>
        <v-spacer></v-spacer>
        <div class="lc-picker__result--item-head-demand">{{ item.demand?.name }}</div>
      </div>
      <template v-if="adminMode">
        <template v-if="item.hasAltNames || item.hasTags">
          <div class="lc-picker__result--item-tags" v-if="item.hasAltNames">
            <v-chip size="small" label variant="outlined" v-for="tag in item.pp_search_altnames_list">{{ tag }}</v-chip>
          </div>
          <div class="lc-picker__result--item-tags" v-if="adminMode && item.hasTags">
            <v-chip size="x-small" v-for="tag in item.pp_search_tags_list">{{ tag }}</v-chip>
          </div>
        </template>
        <v-divider class="my-2"></v-divider>
        <div class="lc-picker__result--item-location">
          <v-chip prepend-icon="mdi-domain" variant="text">{{ item.location.room }}</v-chip>
          <template v-if="!!item.location.cab">
            <v-chip prepend-icon="mdi-fridge" variant="text">{{ item.location.cab }}</v-chip>
          </template>
          <div v-if="!!item.location.exact">
            <v-chip prepend-icon="mdi-archive-marker-outline" variant="text">{{ item.location.exact }}</v-chip>
          </div>
        </div>
        <v-divider class="my-2"></v-divider>
        <div class="lc-picker__result--item-stock">
          <v-chip prepend-icon="mdi-gauge-low" label>Min: {{ item.min_stock }} {{ item.basesize.unit }}</v-chip>
          <v-chip prepend-icon="mdi-gauge-full" label>Max: {{ item.max_stock }} {{ item.basesize.unit }}</v-chip>
          <v-spacer></v-spacer>
          <v-chip
            :color="item.expiryColor" variant="flat" label><b>{{ item.expiryText }}</b></v-chip>
          <v-chip
            :color="item.quantityColor" variant="flat" label><b>{{ item.demanded_quantity }} {{ item.basesize.unit }}</b></v-chip>
          <v-chip v-if="item.current_quantity !== item.demanded_quantity"
            variant="flat" label>Ohne Bestellung:&nbsp;<b>{{ item.current_quantity }} {{ item.basesize.unit }}</b></v-chip>
        </div>
      </template>
      <template v-else>
        <template v-if="item.hasAltNames">
          <div class="lc-picker__result--item-tags" v-if="item.hasAltNames">
            <v-chip size="small" label variant="outlined" v-for="tag in item.pp_search_altnames_list">{{ tag }}</v-chip>
          </div>
        </template>
        <template v-if="item.isOnBooking">
          <v-divider class="my-2"></v-divider>
          <div class="d-flex flex-row-reverse">
            <v-chip color="black" label prepend-icon="mdi-check-circle" variant="flat">Schon im Warenkorb:&nbsp;<b>{{ item.bookingText }}</b></v-chip>
          </div>
        </template>
      </template>
    </v-card>

    <template v-if="hasExactlyOneResult">
      <div class="lc-picker__result--enterhint" >
        Drücke <kbd sym>&crarr;</kbd> um <b>&nbsp;{{ getFirstResult().name }}&nbsp;</b> auszuwählen.
      </div>
    </template>

    <div class="lc-picker__result--overlay" v-if="isTyping">
      <div class="lc-picker__result--overlay-text">Suche ...</div>
    </div>

  </section>

</template>
<style lang="scss" scoped>
.lc-picker {

  display: flex;
  gap: .5rem;

  &__scanner,
  &__action {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 8rem;
    height: 8rem;
  }
  &__scanner {
    background: var(--accent-secondary-background);
  }

  &__description {
    flex: 1;
    border: .5rem solid var(--accent-secondary-background);
    background: var(--accent-secondary-background);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: end;

    & > * {
      opacity: .3;
    }
    &-title {
      font-weight: bold;
      font-size: 1.3rem;
    }
  }

  &__search {
    flex: 1;
    border: .5rem solid var(--accent-secondary-background);
    padding: 1.7rem;
    &-noscan {
      padding: 0;
    }
  }

  &__result {

    position: absolute;
    top: 16.5rem;
    width: 850px;
    height: 100%;
    margin-top: .5rem;

    background: var(--main-light);

    overflow-y: auto;
    overflow-x: hidden;
    z-index: 999;

    &--item {

      background: var(--accent-secondary-background);
      padding: .5rem;
      margin-bottom: .5rem;
      border-radius: 0;

      &-head {

        display: flex;
        justify-content: space-between;
        gap: .5rem;

        &-name {
          font-weight: bold;
          font-size: 1.2rem;
        }
        &-demand {
          background: var(--accent-primary-background);
          color: var(--accent-primary-foreground);
          padding: 2px 1rem;
        }

      }
      &-tags {
        display: flex;
        gap: 4px;
        align-items: center;
        margin-top: 4px;
      }
      &-stock {
        display: flex;
        gap: 4px;
      }


    }

    &--enterhint {
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 0.8rem;
      opacity: 0.8;
    }

    &--overlay {
      background-color: var(--overlay-translucent-background);
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      &-text {
        background-color: var(--accent-secondary-background);
        color: var(--accent-secondary-foreground);
        padding: .5rem 1.5rem;
      }
    }

  }

}
</style>
