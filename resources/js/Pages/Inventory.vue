<script setup>

/**
 * Inventory - Page component
 *
 * This page enables editing items.
 * Additionally it show info for easier management.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, nextTick, watch, toRef, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { useBaseSize } from '@/Composables/useBaseSize'
  import { useOptimalSize } from '@/Composables/useOptimalSize'
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'
  import { useIsPwa } from '@/Composables/useIsPwa'
  const { isPwa } = useIsPwa()
  import { useSpeech } from '@/Composables/useSpeech'
  const { speakNumber, isSpeaking, stop } = useSpeech()

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcButton from '@/Components/LcButton.vue'
  import LcStockAmount from '@/Components/LcStockAmount.vue'
  import LcButtonGroup from '@/Components/LcButtonGroup.vue'
  import LcItemAmountDialog from '@/Dialogs/LcItemAmountDialog.vue'
  import LcTrend from '@/Components/LcTrend.vue'

  // 3rd party components
  import axios from 'axios'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    demands: {
      type: Array,
      required: true,
    },
  })

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openWelcome() {
    router.get('/')
  }
  function openConfigDemands() {
    router.get('/config-demands')
  }
  function openConfigUsages() {
    router.get('/config-usages')
  }

  // #region KeyboardShortcuts

    const handleEsc = () => {
      if (isItemSelected.value) {
        clearSelectedItem()
      } else {
        openWelcome()
      }
    }
    const handleEnter = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        saveItem()
      }
    }

    const handleLeft = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        itemForm.current_quantity -= 1
      }
    }
    const handleRight = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        itemForm.current_quantity += 1
      }
    }

  // #endregion

// #endregion

// #region Inventory-Logic

  // #region Modes

    const inventoryMode = ref('edit')
    const inEditMode = computed(() => inventoryMode.value == 'edit')
    const inCheckMode = computed(() => inventoryMode.value == 'check')

    const toEditMode = () => {
      inventoryMode.value = 'edit'
      editorAccordionOpened.value = []
    }

    const toCheckMode = () => {
      inventoryMode.value = 'check'
      editorAccordionOpened.value = [ 4, 5 ]
    }

  // #endregion

  // #region Dashboard

    // FilterProps
    const itemsNearExpiry = computed(() => {
      const thresholdDate = (new Date()); thresholdDate.setDate(thresholdDate.getDate() + 21); thresholdDate.setHours(0, 0, 0, 0);
      return inventoryStore.items.filter(item => {
        if(!item.current_expiry) { return false }
        const expiryDate = new Date(item.current_expiry)
        return !isNaN(expiryDate) && expiryDate <= thresholdDate
      })
    })

    const itemsMoreOnVehicle = computed(() => {
      return inventoryStore.items.filter(item => {
        if (!item.current_expiry) { return false }
        return (item.onvehicle_stock >= item.pending_quantity)
      })
    })

    // Getter
    const getExpiryText = (dstr) => {
      return new Date(dstr).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }).replace(' ', '-').replace('.', '');
    }

    // Table
    const tableExpiry = ref([
      { title: 'Name', key: 'name' },
      { title: 'Verfall', key: 'current_expiry' },
      { title: '', key: 'action', sortable: false },
    ])
    const sortExpiry = ref([
      { key: 'current_expiry', order: 'asc' }
    ])

    const tableMoreOnVehicle = ref([
      { title: 'Name', key: 'name' },
      { title: '', key: 'action', sortable: false },
    ])

  // #endregion

  // #region ItemPicker

    const selectedItem = ref(null)
    const isItemSelected = computed(() => !!selectedItem.value)

    const clearSelectedItem = async () => {
      selectedItem.value = null
    }

  // #endregion
  // #region ItemEditor

    const editorTitle = computed(() => {
      if (isNewItem.value) { return 'Neuer Artikel' }
      if (inCheckMode.value) { return `Bestand prüfen: ${itemForm.name}` }
      return `Artikel bearbeiten: ${itemForm.name}`
    })

    const saveBtnLabel = computed(() => {
      if (inCheckMode.value) { return 'Geprüft' }
      return 'Speichern'
    })

    // Form
    const itemForm = useForm({

      id: null,
      name: '',
      search_altnames: '',
      search_tags: '',
      demand_id: null,
      location: { room:'', cab:'', exact:'' },
      min_stock: 0,
      max_stock: 0,
      current_expiry: null,
      current_quantity: 0,
      onvehicle_stock: 0,
      checked_at: null,
      max_order_quantity: 0,
      max_bookin_quantity: 0,

      sizes: [],

      stockchangeReason: -1,

    })

    const itemStats = ref({ nostats: true })
    const itemStatsLoading = ref(false)

    const itemFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
        router.reload()
        clearSelectedItem()
        inventoryStore.fetchStore(true)
      },
    }

    // OpenMethods
    const createNew = () => {

      inventoryMode.value = 'edit'
      itemForm.reset()

      itemForm.id = null
      itemForm.name = 'Neuer Artikel'
      itemForm.search_altnames = ''
      itemForm.search_tags = ''
      itemForm.demand_id = props.demands[0]?.id
      itemForm.location = {
        room: 'Lagerraum',
        cab: '',
        exact: '',
      }
      itemForm.min_stock = 0
      itemForm.max_stock = 0
      itemForm.onvehicle_stock = 1

      itemForm.current_expiry = null
      currentExpiryMonth.value = new Date().getMonth() + 1
      currentExpiryYear.value = (new Date()).getFullYear()
      currentNoExpiry.value = true
      updateExpiry()

      itemForm.current_quantity = 0
      itemForm.checked_at = null
      itemForm.max_order_quantity = 0
      itemForm.max_bookin_quantity = 0

      itemForm.sizes.splice(0, itemForm.sizes.length)
      itemForm.sizes.push({ id: null, unit: 'Stk.', amount: 1, is_default: true })

      editorAccordionOpened.value = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ]
      selectedItem.value = { new: true }

      itemStats.value = { nostats: true }
      itemStatsLoading.value = false

    }
    const editItem = (item) => {

      itemForm.reset()

      itemForm.id = item.id
      itemForm.name = item.name
      itemForm.search_altnames = item.search_altnames
      itemForm.search_tags = item.search_tags
      itemForm.demand_id = item.demand_id
      itemForm.location = {
        room: item.location.room,
        cab: item.location.cab,
        exact: item.location.exact,
      }
      itemForm.min_stock = item.min_stock
      itemForm.max_stock = item.max_stock
      itemForm.onvehicle_stock = item.onvehicle_stock

      itemForm.current_expiry = !item.current_expiry ? null : new Date(item.current_expiry)
      currentNoExpiry.value = !item.current_expiry
      currentExpiryMonth.value = !itemForm.current_expiry ? null : itemForm.current_expiry.getMonth()+1
      currentExpiryYear.value = itemForm.current_expiry?.getFullYear() ?? null

      itemForm.current_quantity = item.current_quantity
      itemForm.checked_at = !item.checked_at ? null : new Date(item.checked_at)
      itemForm.max_order_quantity = item.max_order_quantity
      itemForm.max_bookin_quantity = item.max_bookin_quantity

      itemForm.sizes = item.sizes
      itemForm.stockchangeReason = -1

      editorAccordionOpened.value = inCheckMode.value ? [ 4, 5 ] : []
      selectedItem.value = { edit: true }

      loadStats()
      loadSpeechIfNecessary()

    }
    const changeItem = (item) => {
      clearSelectedItem()
      editItem(item)
    }

    // ActionMethods
    const saveItem = async () => {

      if (isNewItem.value) {
        itemForm.post('/inventory', itemFormOptions)
      } else {
        itemForm.put(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }
    const deleteItem = () => {

      if (confirm('Do you really want to delete this?')) {
        itemForm.delete(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }

    // #region TemplateProps

      const isNewItem = computed(() => itemForm.id === null)
      const editorAccordionOpened = ref([])

      // Validation
      const isValidName = computed(() => itemForm.name.trim().length>0 )
      const isValidDemand = computed(() => !!itemForm.demand_id )
      const isValidItem = computed(() => isValidName.value && isValidDemand.value )

      // Check
      const lastCheckedLabel = computed(() => {
        if (!itemForm.checked_at) { return 'Nie geprüft' }
        const day = itemForm.checked_at.getDate()
        const month = itemForm.checked_at.getMonth()+1
        const year = itemForm.checked_at.getFullYear()
        return `${day}.${month}.${year}`
      })

      const isSizeDialogVisible = ref(false)
      const isStockDialogVisible = ref(false)

    // #endregion

    // #region Sizes-Group

      // DialogProps
      const isEditSizeNew = computed(() => !currentEditSizeItem.value?.origOneUnit )

      const isEditSizeUnitExisting = computed(() => {
        return itemForm.sizes.some(e => (
          (e.unit.toLowerCase() === currentEditSizeItem.value?.unit.toLowerCase()) &&
          (e.unit.toLowerCase() !== currentEditSizeItem.value?.origOneUnit)
        ))
      })
      const isEditSizeAmountExisting = computed(() => {
        return itemForm.sizes.some(e => (
          (e.amount === currentEditSizeItem.value?.amount) &&
          (e.amount !== currentEditSizeItem.value?.origOneAmount)
        ))
      })
      const isEditSizeEmpty = computed(() => {
        return !currentEditSizeItem.value?.unit || !currentEditSizeItem.value?.amount
      })
      const isEditSizeDefault = computed(() => {
        return currentEditSizeItem.value?.defaultOne ?? false
      })
      const isEditSizeTooLow = computed(() => {
        return ((currentEditSizeItem.value?.amount < 2) && !currentEditSizeItem.value?.defaultOne)
      })
      const isValidSizeEdit = computed(() => {
        return !isEditSizeEmpty.value && !isEditSizeUnitExisting.value && !isEditSizeAmountExisting.value;
      })

      const currentEditSizeItem = ref({
        id: null,
        item_id: null,
        unit: '',
        amount: 0,
        is_default: false,
        origOneUnit: '',
        origOneAmount: 0,
        defaultOne: false,
      })

      // OpenMethods
      const beginAddSize = async () => {
        currentEditSizeItem.value = {
          id: null,
          item_id: null,
          unit: 'Stk-'+(itemForm.sizes.length+1),
          amount: Math.max(...itemForm.sizes.map(obj => obj.amount))+1,
          is_default: false,
          origOneUnit: '',
          origOneAmount: 0,
          defaultOne: false,
        }
        isSizeDialogVisible.value = true
        await nextTick()
        document.getElementById('id-editsize-amount')?.select()
        document.getElementById('id-editsize-amount')?.focus()
      }
      const beginEditSize = (item) => {
        currentEditSizeItem.value = {
          id: item.id ?? null,
          item_id: item.item_id ?? null,
          unit: item.unit,
          amount: item.amount,
          is_default: item.is_default,
          origOneUnit: (' ' + item.unit.toLowerCase()).slice(1),
          origOneAmount: item.amount,
          defaultOne: item.amount === 1,
        }
        isSizeDialogVisible.value = true
      }

      // ActionMethods
      const cancelEditSize = () => {
        currentEditSizeItem.value = null
        isSizeDialogVisible.value = false
      }
      const deleteEditSize = (item) => {
        const toDelete = itemForm.sizes.find(e => e.unit === item.unit)
        isSizeDialogVisible.value = false
      }
      const acceptEditSize = () => {
        if (isEditSizeNew.value) {

          // append new size
          if (currentEditSizeItem.value.is_default) {
            itemForm.sizes.forEach( item => item.is_default = false)
          }
          itemForm.sizes.push(currentEditSizeItem.value)

        } else {

          // edit size
          const existing = itemForm.sizes.find(e => e.amount === currentEditSizeItem.value.origOneAmount)
          if (!!existing) {
            if (currentEditSizeItem.value.is_default) {
              itemForm.sizes.forEach( item => item.is_default = false)
            }
            existing.unit = currentEditSizeItem.value.unit
            existing.amount = currentEditSizeItem.value.amount
            existing.is_default = currentEditSizeItem.value.is_default
            if (existing.amount === 1 && !existing.is_default && !itemForm.sizes.some( item => item.is_default )) {
              existing.is_default = true
            }
          }

        }
        cancelEditSize()
      }

      // #region Table

        const sizesHeaders = ref([
          { title: 'Einheit', key: 'unit', minWidth: '30%' },
          { title: 'Menge', key: 'amount', minWidth: '20%' },
          { title: 'Bearbeiten', key: 'action', sortable: false },
        ])
        const sizesSortBy = ref([
          { key: 'amount', order: 'asc' }
        ])

      // #endregion

    // #endregion
    // #region MinMax-Group

      // Props
      const { baseUnit } = useBaseSize(toRef(itemForm, 'sizes'))

    // #endregion
    // #region Quantity/Expiry-Group

      const currentExpiryMonth = ref(null)
      const currentExpiryYear = ref(null)
      const currentNoExpiry = ref(true)
      const selectableStockChangeReasons = ref([
        { name: "Abweichung", value: -1 },
        { name: "Rückbuchung", value: -2 },
        { name: "Verfall", value: -3 },
      ])

      const isValidExpiry = computed(() => currentNoExpiry.value || (itemForm.current_expiry !== null && !isNaN(itemForm.current_expiry)))

      // Expiry-Props
      const updateExpiry = () => {
        if (currentNoExpiry.value) {
          itemForm.current_expiry = null
        } else if (!currentExpiryMonth.value || !currentExpiryYear.value) {
          itemForm.current_expiry = null
        } else {
          itemForm.current_expiry = new Date(currentExpiryYear.value, currentExpiryMonth.value, 0)
        }
      }
      watch(currentExpiryMonth, () => {
        updateExpiry()
      })
      watch(currentExpiryYear, () => {
        updateExpiry()
      })
      watch(currentNoExpiry, () => {
        updateExpiry()
      })

      const loadSpeechIfNecessary = () => {
        if (inCheckMode.value && !isNewItem.value) {
          setTimeout(() => speakNumber(itemForm.current_quantity), 500)
        }
      }

    // #endregion
    // #region Stats-Group

      const loadStats = async () => {

        if (!itemForm.id) { return }
        itemStatsLoading.value = true

        try
        {
          const response = await axios.get('/api/statistic?item=' + itemForm.id);
          itemStats.value = response.data
        }
        catch (err)
        {
          console.error('Failed to fetch item stats:', err);
        }
        finally
        {
          itemStatsLoading.value = false
        }

      }

      const hasTrend = computed(() => !!itemStats?.value.trend)

      const convertDate = (date) => {
        const obj = new Date(date)
        if (obj.isNaN) { return '' }
        const day = obj.getDate().toString().padStart(2, '0')
        const month = (obj.getMonth()+1).toString().padStart('0', 2)
        return `${day}.${month}.${obj.getFullYear()}`
      }

    // #endregion

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerLeft(handleLeft)
    InputService.registerRight(handleRight)
    InputService.registerEsc(handleEsc)
    InputService.registerEnter(handleEnter)
    inventoryStore.fetchStore(true)
    document.body.classList.remove('cursor-off')
  })
  onUnmounted(() => {
    InputService.unregisterLeft(handleLeft)
    InputService.unregisterRight(handleRight)
    InputService.unregisterEsc(handleEsc)
    InputService.unregisterEnter(handleEnter)
  })

// #endregion

</script>

<template>

  <Head title="Inventar" />

  <div class="page-inventory">

    <LcPagebar :title="isPwa ? 'Inventar-App' : 'Inventar'" @back="openWelcome" :disabled="isPwa || isItemSelected">
      <template #actions>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openConfigDemands">Anforderungen
        </v-btn>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openConfigUsages">Verwendungen
        </v-btn>
      </template>
    </LcPagebar>

    <section>

      <template v-if="!isItemSelected">

        <LcButtonGroup v-model="inventoryMode" :items="[
          { label: 'Bearbeiten', value: 'edit' },
          { label: 'Prüfen', value: 'check' },
        ]"></LcButtonGroup>

        <LcItemInput
          :result-specs="{ w: 850, i: 19.0 }" :allow-new="inEditMode"
          @create-new="createNew" @select-item="editItem">
        </LcItemInput>

        <!-- DashBoard -->
        <v-card title="Verfall prüfen" class="mt-2" variant="outlined">
          <v-card-text>

            <v-data-table :items="itemsNearExpiry"
              :headers="tableExpiry" :sort-by="sortExpiry"
              hide-default-footer :items-per-page="100">
              <template v-slot:item.demand="{ item }">
                {{ item.demand.name }}
              </template>
              <template v-slot:item.current_expiry="{ item }">
                {{ getExpiryText(item.current_expiry) }}
              </template>
              <template v-slot:item.action="{ item }">
                <v-btn small variant="outlined" @click="editItem(item)">
                  <v-icon icon="mdi-cog"></v-icon>
                </v-btn>
              </template>
            </v-data-table>

          </v-card-text>
        </v-card>

        <v-card title="Fahrzeugbestand prüfen" class="mt-2" variant="outlined">
          <v-card-text>

            <v-data-table :items="itemsMoreOnVehicle"
              :headers="tableMoreOnVehicle"
              hide-default-footer :items-per-page="100">
              <template v-slot:item.action="{ item }">
                <v-btn small variant="outlined" @click="editItem(item)">
                  <v-icon icon="mdi-cog"></v-icon>
                </v-btn>
              </template>
            </v-data-table>

          </v-card-text>
        </v-card>

      </template>
      <template v-else>

        <LcItemInput @select-item="changeItem" hidden></LcItemInput>

        <v-toolbar flat>
          <v-app-bar-nav-icon
            icon="mdi-arrow-left" :disabled="itemForm.processing"
            @click="clearSelectedItem"></v-app-bar-nav-icon>
          <v-toolbar-title>
            {{ editorTitle }}
          </v-toolbar-title>
          <v-toolbar-items>
            <v-btn v-if="inCheckMode"
              @click="toEditMode"
              icon="mdi-wrench"
              v-tooltip:bottom="'Artikel bearbeiten'"
            ></v-btn>
            <v-btn v-if="inEditMode && !isNewItem"
              @click="toCheckMode"
              icon="mdi-check"
              v-tooltip:bottom="'Nur Prüfen'"
            ></v-btn>
          </v-toolbar-items>
        </v-toolbar>

        <v-expansion-panels class="mt-2" flat multiple
          v-model="editorAccordionOpened" :disabled="itemForm.processing">

          <v-expansion-panel class="mt-1" title="Allgemein" color="black" outlined v-show="inEditMode">
            <v-expansion-panel-text>

              <v-text-field v-model="itemForm.name"
                label="Name" required hide-details>
              </v-text-field>
              <v-alert v-if="!isValidName"
                text="Du musst einen Namen angeben."
                type="error">
              </v-alert>

              <v-text-field v-model="itemForm.search_altnames"
                class="mt-2" label="Alternative Namen" hide-details>
              </v-text-field>
              <v-text-field v-model="itemForm.search_tags"
                label="Tags" hide-details>
              </v-text-field>

              <v-select v-model="itemForm.demand_id" :items="demands"
                class="mt-2" label="Anforderung" item-title="name"
                item-value="id" required hide-details>
              </v-select>
              <v-alert v-if="!isValidDemand"
                text="Du musst eine Anforderung angeben."
                type="error">
              </v-alert>

            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Wo ist ... ?" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-text-field v-model="itemForm.location.room"
                label="Raum" hide-details>
              </v-text-field>

              <v-text-field v-model="itemForm.location.cab"
                label="Schrank" hide-details>
              </v-text-field>

              <v-text-field v-model="itemForm.location.exact"
                label="Genauer Ort (z.B. Schublade)" hide-details>
              </v-text-field>

            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Packungsgrößen" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-data-table :items="itemForm.sizes"
                :headers="sizesHeaders" :sort-by="sizesSortBy"
                hide-default-footer :items-per-page="100">
                <template v-slot:item.action="{ item }">
                  <v-btn small variant="outlined" @click="beginEditSize(item)">
                    <v-icon icon="mdi-cog"></v-icon>
                  </v-btn>
                </template>
              </v-data-table>

              <v-divider></v-divider>

              <v-btn color="primary" variant="outlined" class="mt-4" prepend-icon="mdi-plus"
                @click="beginAddSize()">Hinzufügen
              </v-btn>

              </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Min- & Maxbestand" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-form class="mb-6">

                <LcStockAmount
                  v-model:stock="itemForm.min_stock"
                  :sizes="itemForm.sizes"
                  title="Min-Bestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Min-Bestand zu errechnen."
                  button-text="Min-Bestand ändern">
                </LcStockAmount>

                <LcStockAmount
                  v-model:stock="itemForm.max_stock"
                  :sizes="itemForm.sizes"
                  title="Max-Bestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Max-Bestand zu errechnen."
                  button-text="Max-Bestand ändern">
                </LcStockAmount>

              </v-form>
              <v-form class="mb-6">

                <LcStockAmount
                  v-model:stock="itemForm.onvehicle_stock"
                  :sizes="itemForm.sizes"
                  title="Fahrzeugbestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Fahrzeugbestand zu errechnen."
                  button-text="Fahrzeugbestand ändern">
                </LcStockAmount>

              </v-form>
              <v-form class="pb-3">

                <LcStockAmount
                  v-model:stock="itemForm.max_bookin_quantity"
                  :sizes="itemForm.sizes"
                  title="Max/Buchung berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um die maximal Menge pro Buchung einzugeben."
                  button-text="Max/Buchung ändern">
                </LcStockAmount>

                <LcStockAmount
                  v-model:stock="itemForm.max_order_quantity"
                  :sizes="itemForm.sizes"
                  title="Max/Bestellung berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um die maximal Menge pro Bestellung einzugeben."
                  button-text="Max/Bestellung ändern">
                </LcStockAmount>

              </v-form>


            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Aktueller Bestand" color="black">
            <v-expansion-panel-text>

              <v-form>
                <v-row>
                  <v-col cols="4" class="page-inventory__table--result">
                    Aktueller Bestand ({{ baseUnit }})
                  </v-col>
                  <v-col cols="5">
                    <v-number-input
                      v-model="itemForm.current_quantity"
                      :reverse="false"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="-999"
                      :max="999"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="3">
                    <v-select v-if="!isNewItem"
                      v-model="itemForm.stockchangeReason"
                      :items="selectableStockChangeReasons"
                      label="Grund"
                      item-title="name"
                      item-value="value"
                      required
                      hide-details
                    ></v-select>
                  </v-col>
                </v-row>
                <v-row class="mt-2">
                  <v-col cols="4" class="page-inventory__table--result">
                    Nächster Verfall
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryMonth"
                      :reverse="false" :disabled="currentNoExpiry"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="1"
                      :max="12"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryYear"
                      :reverse="false" :disabled="currentNoExpiry"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="(new Date()).getFullYear() - 1"
                      :max="(new Date()).getFullYear() + 99"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="2">
                    <v-checkbox v-model="currentNoExpiry"
                      label="Ohne MHD" hide-details>
                    </v-checkbox>
                  </v-col>
                </v-row>
                <v-row class="mt-n5">
                  <v-col cols="4"></v-col>
                  <v-col cols="5">
                    <v-alert v-if="!isValidExpiry"
                      text="Gib einen Verfall ein."
                      type="error"></v-alert>
                  </v-col>
                </v-row>
              </v-form>


            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Statistik" color="black" v-if="!itemStats.nostats" v-show="inCheckMode">
            <v-expansion-panel-text>
              <v-container>
                <v-row >
                  <v-col cols="3" class="page-inventory__table--result">Bestellt (Quartal)</v-col>
                  <v-col cols="2">
                    {{ itemStats.ordered_once ? 'Ja' : 'Nein' }}
                  </v-col>
                </v-row>
                <v-row>
                  <v-col cols="3" class="page-inventory__table--result">Verbrauch/Woche</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.ordered_stats.amount_perweek.toFixed(2)} ${baseUnit}` }}
                    <LcTrend :trend="itemStats.trend.trend_perweek" v-if="hasTrend" />
                  </v-col>
                  <v-col cols="3" class="page-inventory__table--result">Abweichung/Woche</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.ordered_stats.changed_perweek.toFixed(2)} ${baseUnit}` }}
                  </v-col>
                </v-row>
                <v-row class="mt-n5">
                  <v-col cols="3" class="page-inventory__table--result">Maximalverbrauch</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.ordered_stats.max_amount.toFixed(0)} ${baseUnit}` }}
                  </v-col>
                  <v-col cols="3" class="page-inventory__table--result">Standardabweichung</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.ordered_stats.std_deviation.toFixed(2)} ${baseUnit}` }}
                  </v-col>
                </v-row>

                <v-row v-if="itemStats.ran_empty.length>0">
                  <v-col cols="3" class="page-inventory__table--result">
                    <v-icon icon="mdi-alert-circle" color="error" class="mr-2"></v-icon>Leer Gelaufen</v-col>
                  <v-col cols="2">
                    <v-chip v-for="date in itemStats.ran_empty">
                      {{ convertDate(date) }}</v-chip>
                  </v-col>
                </v-row>

                <v-row v-if="itemStats.ordered_much.length>0">
                  <v-col cols="3" class="page-inventory__table--result">
                    <v-icon icon="mdi-alert-circle" color="warning" class="mr-2"></v-icon>Viel Bestellt</v-col>
                  <v-col cols="2">
                    <v-chip v-for="item in itemStats.ordered_much" style="max-width:999px">
                      <b>{{ convertDate(item.time) }}</b>: {{ `${item.amount} ${baseUnit}` }}</v-chip>
                  </v-col>
                </v-row>

                <v-row v-if="itemStats.changed_much.length>0">
                  <v-col cols="3" class="page-inventory__table--result">
                    <v-icon icon="mdi-alert-circle" color="warning" class="mr-2"></v-icon>Viel Korrigiert</v-col>
                  <v-col cols="2">
                    <v-chip v-for="item in itemStats.changed_much" style="max-width:999px">
                      <b>{{ convertDate(item.time) }}</b>: {{ `${item.amount} ${baseUnit}` }}</v-chip>
                  </v-col>
                </v-row>

              </v-container>
            </v-expansion-panel-text>
          </v-expansion-panel>

        </v-expansion-panels>

        <!-- DIALOG BUTTONS -->
        <v-card class="mt-2 rounded-0" variant="outlined" :disabled="itemForm.processing">
          <v-card-text class="pa-4">

            <v-form>
              <v-row>
                <v-col cols="3" v-if="!isNewItem && inEditMode">
                  <v-btn color="error" variant="flat" block
                    @click="deleteItem">
                    Löschen
                  </v-btn>
                </v-col>
                <v-col cols="3" style="display:flex" v-if="inCheckMode">
                  <v-chip class="lastcheck-chip" prepend-icon="mdi-progress-clock">
                    <b>{{lastCheckedLabel}}</b></v-chip>
                </v-col>
                <v-col :cols="isNewItem ? 12 : 9">
                  <v-btn color="success" variant="flat" block :disabled="!isValidItem" :loading="itemForm.processing"
                    @click="saveItem">
                    {{ saveBtnLabel }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>

          </v-card-text>
        </v-card>

      </template>

      <!-- ItemSize-Dialog -->
      <v-dialog v-model="isSizeDialogVisible" max-width="420px">
        <v-card prepend-icon="mdi-package-variant-closed" v-if="currentEditSizeItem" class="rounded-0"
          :title="isEditSizeNew ? 'Neue Packungsgröße' : 'Packungsgröße bearbeiten'">
          <v-divider></v-divider>
          <v-card-text>

            <p class="mb-4">
              Gib eine Packungsgröße an.
            </p>

            <v-text-field v-model="currentEditSizeItem.amount"
              :disabled="currentEditSizeItem.defaultOne"
              label="Menge" type="number" :min="2" hide-details>
            </v-text-field>

            <v-text-field v-model="currentEditSizeItem.unit"
              class="mt-2" id="id-editsize-unit"
              label="Größenangabe" hide-details>
            </v-text-field>

            <v-checkbox v-model="currentEditSizeItem.is_default"
              label="In dieser Packungseinheit bestellen" hide-details>
            </v-checkbox>

            <v-alert class="mt-2" v-if="isEditSizeUnitExisting"
              text="Diese Einheit existiert bereits." type="error">
            </v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeAmountExisting"
              text="Diese Menge exisitert bereits." type="error">
            </v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeEmpty"
              text="Du musst alles ausfüllen." type="error">
            </v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeTooLow"
              text="Du kannst nicht unter die Standardgröße." type="error">
            </v-alert>

          </v-card-text>

          <v-divider></v-divider>

          <v-card-actions class="mx-4 mb-2">
            <v-btn color="error" variant="tonal" v-if="!isEditSizeNew && !isEditSizeDefault"
              @click="deleteEditSize">Delete
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn
              @click="cancelEditSize">Abbrechen
            </v-btn>
            <v-btn color="primary" variant="tonal" :disabled="!isValidSizeEdit"
              @click="acceptEditSize">Speichern
            </v-btn>
          </v-card-actions>

        </v-card>
      </v-dialog>

      <!-- Dialogs -->
      <LcItemAmountDialog ref="minmaxDialog" />

    </section>

  </div>

</template>
<style lang="scss" scoped>
.page-inventory {

  &__table--result-centered,
  &__table--result {
    display: flex;
    font-weight: bold;
    align-items: center;
    justify-content: right;

    &-centered {
      justify-content: center;
    }
  }

}
.lastcheck-chip {
  width: 100%;
  height: calc(var(--v-btn-height) + 0px);
}
</style>
