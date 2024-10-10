<script setup>

// #region imports

  // Vue composables
  import { computed, ref, nextTick, onMounted, onUnmounted } from 'vue'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'
  import InputService from '@/Services/InputService'

// #endregion

// #region props/emits

  const emit = defineEmits([
    'createNew',
    'selectItem',
    'ctrlFinish',
  ])
  const props = defineProps({

    items: {
      type: Array,
      required: true,
    },
    booking: {
      type: Array,
      required: false,
      default: [],
    },

    allowScan: {
      type: Boolean,
      required: false,
      default: true,
    },
    adminMode: {
      type: Boolean,
      required: false,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    resultPos: {
      type: Object,
      required: false,
      default: { w: 850, i: 11 },
    },
  })

  const resultPosWidth = computed(() => (props.resultPos?.w ?? 850) + 'px')
  const resultPosIndent = computed(() => (props.resultPos?.i ?? 11) + 'rem')
  
// #endregion

// #region selection

  const hasAnyItems = computed(() => props.items.length > 0)

  const selectItemBySearch = (item) => {
    emit('selectItem', item, null)
    changeModeToScan()
    searchText.value = ''
  }
  const selectItemByScan = (item, amount) => {
    emit('selectItem', item, amount)
  }
  const createNew = () => {
    emit('createNew')
    changeModeToScan()
    searchText.value = ''
  }

// #endregion

// #region mode

  // props
  const currentMode = ref(props.allowScan ? 'SCAN' : 'TEXT')
  const isInScanMode = computed(() => currentMode.value === 'SCAN')
  const isInTextMode = computed(() => currentMode.value === 'TEXT')

  // methods
  const changeModeToScan = () => {
    if (!props.allowScan) { return }
    searchText.value = ''
    currentMode.value = 'SCAN'
  }
  const changeModeToText = async (text = '') => {
    searchText.value = text
    currentMode.value = 'TEXT'
    await nextTick()
    document.getElementById('id-picker-searchbox')?.focus()
  }

// #endregion

// #region scan-mode

  const pickerDescriptionTitle = computed(() => {
    if (!hasAnyItems.value) { return 'Kein Material angelegt' }
    if (props.disabled) { return '' }
    return 'Scanne oder suche dein Material ...'
  })

// #endregion

// #region text-mode

  // props
  const searchText = ref('')

  const bookingsMap = computed(() => {
    const map = new Map()
    props.booking.forEach(booking => {
      map.set(booking.item_id, booking)
    })
    return map
  })
  const itemsMap = computed(() => {
    return props.items.map(item => ({
      ...item,
      pp_name: item.name.toLowerCase(),
      pp_search_altnames: item.search_altnames ? item.search_altnames.toLowerCase() : '',
      pp_search_tags: item.search_tags ? item.search_tags.toLowerCase() : '',
    }))
  })

  const filteredItems = computed(() => {

    // search text
    const lcSearchText = searchText.value.toLowerCase()
    const results = []
    const bookings = bookingsMap.value
    const items = itemsMap.value
    
    for (const item of items) {

      // calc score based on search criteria
      let score = 0
      if (item.pp_name.startsWith(lcSearchText)) { score += 10 }
      else if (item.pp_name.includes(lcSearchText)) { score += 2 }
      if (item.pp_search_altnames.includes(lcSearchText)) { score += 3 }
      if (item.pp_search_tags.includes(lcSearchText)) { score += 1 }

      // compute additional props if matches at least the name
      if (score > 1) {

        const hasAltNames = !!item.search_altnames?.trim()
        const taggedAltNames = hasAltNames 
          ? getExplodedTags(item.search_altnames)
          : null;

        const hasTags = !!item.search_tags?.trim()
        const taggedTags = hasTags
          ? getExplodedTags(item.search_tags)
          : null;

        const quantityColor = getQuantityColor(item)
        const expiryColor = getExpiryColor(item)
        const expiryText = getExpiryText(item)

        const bookingEntry = bookings.get(item.id) || null
        const isOnBooking = bookingEntry !== null
        const bookingText = isOnBooking
          ? `${bookingEntry.item_amount} ${item.basesize.unit}`
          : null;

        results.push({
          ...item,
          score,
          hasAltNames,
          taggedAltNames,
          hasTags,
          taggedTags,
          quantityColor,
          expiryColor,
          expiryText,
          isOnBooking,
          bookingText,
        });

      }

    }

    // sort if necessary
    if (results.length > 1) {
      results.sort((a, b) => b.score - a.score)
      results.splice(4)
    }

    return results

  })

  const isSearching = computed(() => searchText.value.trim().length > 0)
  const hasAnyResults = computed(() => filteredItems.value.length > 0)
  const hasOneResult = computed(() => filteredItems.value.length === 1)

  // methods
  const getFirstResult = () => { return filteredItems.value[0] ?? null }

  const selectItem = () => {
    if (hasOneResult.value) {
      // select only item
      selectItemBySearch(getFirstResult())
    }
    if (searchText.value.startsWith('LC-')) {
      // scanned something while in text-mode
      searchText.value = ''
    }
  }
  const findItem = (code) => {

    if (!isInScanMode.value) {
        changeModeToScan()
      }

    // fetch ctrl codes
    if (code === 'LC-2000001000') {
      emit('ctrlFinish')
      return
    }
    
    // search item
    const item = props.items.find(item => item.barcodes.hasOwnProperty(code)) ?? null
    if (!item) { return }

    // find itemsize
    const amount = item.barcodes[code]
    selectItemByScan(item, amount)

  }
  
  // #region input

    const receiveKeys = async (keys) => {
      if (isInScanMode.value) {
        changeModeToText()
      }
      if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
        searchText.value += keys
        currentMode.value = 'TEXT'
        await nextTick()
        document.getElementById('id-picker-searchbox')?.focus()
      }
    }
    const receiveBackspace = async () => {
      if (isInScanMode.value) {
        return
      }
      if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
        await nextTick()
        document.getElementById('id-picker-searchbox')?.focus()
      }
    }

    const handleEscape = () => {
      if (isInTextMode.value) {
        changeModeToScan()
        return false
      } 
      return true
    }

  // #endregion

  // #region results

    const showWholeItem = computed(() => props.adminMode)
    const cssResultPane = computed(() => `height:calc(100% - 0.5rem - ${resultPosIndent.value}); top: ${resultPosIndent.value}; width: ${resultPosWidth.value};`)

    const getExplodedTags = (tags) => {
      if (!tags) { return [] }
      const trimmedInput = tags.trim().replace(/^,|,$/g, '')
      const tagsArray = trimmedInput.split(',')
      return tagsArray.map(tag => tag.trim()).filter(tag => tag !== '')
    }

    const getQuantityColor = (item) => {
      if (item.demanded_quantity <= item.min_stock) { return 'error' }
      else if (item.demanded_quantity <= (item.min_stock + item.max_stock/2)) { return 'warning' }
      else { return 'success' }
    }

    const getExpiryColor = (item) => {
      const daysDiff = Math.round(
        (new Date(item.current_expiry).getTime() - new Date().getTime()) / 
        (1000 * 60 * 60 * 24))
      if (daysDiff > 21) { return 'success' }
      else if (daysDiff > 14) { return 'warning' }
      else { return 'error' }
    }

    const getExpiryText = (item) => {
      if (!item.current_expiry) { return "n/v" }
      return new Date(item.current_expiry).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }).replace(' ', '-').replace('.', '');
    }

  // #endregion

// #endregion

// #region mount/unmount

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
// #region expose

  defineExpose({ handleEscape })

// #endregion

</script>
<template>

    <div class="lc-picker" v-if="isInScanMode">

      <div class="lc-picker--scanner">
        <LcScanIndicator 
          :active="hasAnyItems && !disabled">
        </LcScanIndicator>
      </div>
      <div class="lc-picker--description">
        <div class="lc-picker--description_title">
          {{ pickerDescriptionTitle }}
        </div>
      </div>

      <LcButton v-if="hasAnyItems && !disabled"
        class="lc-picker--btn" type="secondary" icon="mdi-form-textbox"
        @click="changeModeToText()">Suchen
      </LcButton>
      <LcButton v-if="adminMode && !disabled"
        class="lc-picker--btn" type="secondary"icon="mdi-plus"
        @click="createNew">Neu
      </LcButton>

    </div>
    <div class="lc-picker" v-else-if="isInTextMode">

      <LcButton v-if="allowScan"
        class="lc-picker--btn" type="secondary" icon="mdi-arrow-left"
        @click="changeModeToScan">Zurück
      </LcButton>

      <div class="lc-picker--search" :class="{ 'lc-picker--search-noscan': !allowScan }">
        <v-text-field label="Suche nach Material ..." variant="outlined" hide-details
          id="id-picker-searchbox" v-model="searchText" :rounded="0"
          @keyup.enter="selectItem"></v-text-field>
      </div>

    </div>
    <div class="lc-pickerresult" v-if="hasAnyItems && isSearching" :style="cssResultPane">

      <v-empty-state v-show="!hasAnyResults"
        text="Versuche einen anderen Suchbegriff"
        title="Kein Material gefunden"
      ></v-empty-state>

      <v-card class="lc-pickerresult-item rounded-0" v-for="item in filteredItems" link variant="flat" 
        @click="selectItemBySearch(item)">
        <div class="lc-pickerresult-item--head-wrapper">
          <div class="lc-pickerresult-item--name">{{ item.name }}</div>
          <v-spacer></v-spacer>
          <div class="lc-pickerresult-item--demand">{{ item.demand?.name }}</div>
        </div>
        <template v-if="item.hasAltNames || item.hasTags">
          <div class="lc-pickerresult-item--tags-wrapper mt-1" v-if="item.hasAltNames">
            <v-chip size="small" label variant="outlined" v-for="tag in item.taggedAltnames">{{ tag }}</v-chip>
          </div>
          <div class="lc-pickerresult-item--tags-wrapper mt-1" v-if="showWholeItem && item.hasTags">
            <v-chip size="x-small" v-for="tag in item.taggedTags">{{ tag }}</v-chip>
          </div>
        </template>
        <v-divider class="my-2" v-if="showWholeItem"></v-divider>
        <div class="lc-pickerresult-item--location-wrapper" v-if="showWholeItem">
          <div class="lc-pickerresult-item--location-coarse">
            <v-chip prepend-icon="mdi-domain" variant="text">{{ item.location.room }}</v-chip>
            <template v-if="!!item.location.cab">
              <v-chip prepend-icon="mdi-fridge" variant="text">{{ item.location.cab }}</v-chip>
            </template>
            <div v-if="!!item.location.exact">
              <v-chip prepend-icon="mdi-archive-marker-outline" variant="text">{{ item.location.exact }}</v-chip>
            </div>
          </div>
        </div>
        <v-divider class="my-2" v-if="showWholeItem"></v-divider>
        <div class="lc-pickerresult-item--stock-wrapper" v-if="showWholeItem">
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
        <template v-if="item.isOnBooking">
          <v-divider class="my-2"></v-divider>
          <div class="d-flex flex-row-reverse">
            <v-chip color="black" label prepend-icon="mdi-check-circle" variant="flat">Schon im Warenkorb:&nbsp;<b>{{ item.bookingText }}</b></v-chip>
          </div>
        </template>
      </v-card>

      <template v-if="hasOneResult">
        <div class="lc-pickerresult--hint-enter" >
          Drücke <kbd sym>&crarr;</kbd> um <b>&nbsp;{{ getFirstResult().name }}&nbsp;</b> auszuwählen.
        </div>
      </template>

    </div>

</template>
<style lang="scss" scoped>
.lc-picker {

  display: flex;
  gap: .5rem;

  &--scanner,
  &--btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 8rem;
    height: 8rem;
  }
  &--scanner {
    background: var(--lc-secondary-accent-background);
  }

  &--description {
    flex: 1;
    border: .5rem solid var(--lc-secondary-accent-background);
    background: var(--lc-secondary-accent-background);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: end;

    & > * {
      opacity: .3;
    }
    &_title {
      font-weight: bold;
      font-size: 1.3rem;
    }
    &_subtitle {
      font-size: .9rem;
    }
  }

  &--search {
    flex: 1;
    border: .5rem solid var(--lc-secondary-accent-background);
    padding: 1.7rem;
    &-noscan {
      padding: 0;
    }
  }

}
.lc-pickerresult {

  position: absolute;
  top: 16.5rem;
  background: var(--lc-main-background);
  z-index: 999;
  width: 850px;
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;

  margin-top: .5rem;
  border-bottom: .5rem solid var(--lc-secondary-accent-background);

  &-item {

    background: var(--lc-secondary-accent-background);
    padding: .5rem;
    margin-bottom: .5rem;

    &--head-wrapper {
      display: flex;
      justify-content: space-between;
      gap: .5rem;
    }

    &--name {
      font-weight: bold;
      font-size: 1.2rem;
    }

    &--demand {
      background: var(--lc-primary-accent-background);
      color: var(--lc-primary-accent-text);
      padding: 2px 1rem;
    }
    &--booking {
      background: var(--lc-main-text);
      color: var(--lc-main-background);
      padding: 2px 1rem;
    }

    &--tags-wrapper {
      display: flex;
      gap: 4px;
      align-items: center;
    }

    &--stock-wrapper {
      display: flex;
      gap: 4px;
    }

  }

  &--hint-enter {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 0.8rem;
    opacity: 0.8;
  }

}
</style>
