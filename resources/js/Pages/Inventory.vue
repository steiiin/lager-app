<script setup>

// #region imports

  // Vue composables
  import { ref, computed, nextTick, watch, toValue, toRef, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Vue components
  import { VNumberInput } from 'vuetify/labs/VNumberInput'

  // Local composables
  import { useBaseSize, useSizesCalc } from '@/Composables/CalcSizes'
  import CursorHandler from '@/Components/CursorHandler.vue'
  import InputService from '@/Services/InputService'

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcCalcMinMaxDialog from '@/Dialogs/LcCalcMinMaxDialog.vue'

// #endregion

// #region props

  const props = defineProps({
    items: {
      type: Array,
      required: true,
    },
    demands: {
      type: Array,
      required: true,
    },
  })

// #endregion

// #region navigation

  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  function openWelcome() {
    router.get('/')
  }
  function openConfigDemands() {
    router.get('/config-demands')
  }
  function openConfigUsages() {
    router.get('/config-usages')
  }

// #endregion

// #region items

  // #region dashboard

    const itemsNearExpiry = computed(() => {
      const thresholdDate = (new Date()); thresholdDate.setDate(thresholdDate.getDate() + 21); thresholdDate.setHours(0, 0, 0, 0);
      return props.items.filter(item => {
        const expiryDate = new Date(item.current_expiry)
        return !isNaN(expiryDate) && expiryDate <= thresholdDate
      })
    })

    const tableExpiry = ref([
      { title: 'Name', key: 'name' },
      { title: 'Verfall', key: 'current_expiry' },
      { title: '', key: 'action', sortable: false },
    ])
    const sortExpiry = ref([
      { key: 'current_expiry', order: 'asc' }
    ])

    const itemsOrdered = computed(() => {
      const thresholdDate = (new Date()); thresholdDate.setDate(thresholdDate.getDate() + 21); thresholdDate.setHours(0, 0, 0, 0);
      return props.items.filter(item => {
        return item.current_quantity < item.demanded_quantity
      })
    })

    const tableOrdered = ref([
      { title: 'Name', key: 'name' },
      { title: 'Verfall', key: 'current_expiry' },
      { title: '', key: 'action', sortable: false },
    ])
    const sortOrdered = ref([
      { key: 'current_expiry', order: 'asc' }
    ])

  // #endregion

  // #region form-data

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

      sizes: [],

      stockchangeReason: -1,

    })
    const itemFormOptions = {
      preserveScroll: true,
      onSuccess: () => {
        router.reload()
        clearSelectedItem()
      },
    }

  // #endregion

  // #region select

    const selectedItem = ref(null)
    const isItemSelected = computed(() => {
      return !!selectedItem.value
    })

    const clearSelectedItem = async () => {
      selectedItem.value = null
    }

  // #endregion

  // #region editor

    // state
    const editActivePanel = ref(null)

    // computed
    const isNewItem = computed(() => itemForm.id === null)

    // methods
    const createNew = () => {

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
      
      itemForm.current_expiry = null
      currentExpiryMonth.value = new Date().getMonth() + 1
      currentExpiryYear.value = (new Date()).getFullYear()
      updateExpiry()

      itemForm.current_quantity = 0

      itemForm.sizes.splice(0, itemForm.sizes.length)
      itemForm.sizes.push({ id: null, unit: 'Stk.', amount: 1, is_default: true })

      editActivePanel.value = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ]
      selectedItem.value = { new: true }

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

      itemForm.current_expiry = new Date(item.current_expiry)
      currentExpiryMonth.value = itemForm.current_expiry.getMonth()+1
      currentExpiryYear.value = itemForm.current_expiry.getFullYear()

      itemForm.current_quantity = item.current_quantity

      itemForm.sizes = item.sizes
      item.stockchangeReason = -1

      editActivePanel.value = [ 4 ]
      selectedItem.value = { edit: true }

    }

    const saveItem = async () => {

      if (isNewItem.value) {
        itemForm.post('/inventory', itemFormOptions)
      } else {
        itemForm.put(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }
    const deleteItem = () => {

      // TODO: create real confirm
      if (confirm('really delete this?')) {
        itemForm.delete(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }

    // #region editor-general

      const isValidName = computed(() => itemForm.name.trim().length>0 )
      const isValidDemand = computed(() => !!itemForm.demand_id )
      const isValidItem = computed(() => isValidName.value && isValidDemand.value )

    // #endregion

    // #region editor-size

      // #region data-table

        const sizesHeaders = ref([
          { title: 'Einheit', key: 'unit', minWidth: '30%' },
          { title: 'Menge', key: 'amount', minWidth: '20%' },
          { title: 'Bearbeiten', key: 'action', sortable: false },
        ])

        const sizesSort = ref([
          { key: 'amount', order: 'asc' }
        ])

      // #endregion

      const isEditSizeVisible = ref(false)
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
        isEditSizeVisible.value = true
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
        isEditSizeVisible.value = true
      }
      const cancelEditSize = () => {
        currentEditSizeItem.value = null
        isEditSizeVisible.value = false
      }
      const deleteEditSize = (item) => {
        const toDelete = itemForm.sizes.find(e => e.unit === item.unit)
        isEditSizeVisible.value = false
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

    // #endregion

    // #region editor-minmax

      // computed 
      const { baseUnit } = useBaseSize(toRef(itemForm, 'sizes'))
      const { text: minText } = useSizesCalc(toRef(itemForm, 'sizes'), toRef(itemForm, 'min_stock'))
      const { text: maxText } = useSizesCalc(toRef(itemForm, 'sizes'), toRef(itemForm, 'max_stock'))

      const minDefaultText = computed(() => `${itemForm.min_stock} ${baseUnit.value}`)
      const maxDefaultText = computed(() => `${itemForm.max_stock} ${baseUnit.value}`)
      
      const minSizesDiffer = computed(() => minText.value != minDefaultText.value)
      const maxSizesDiffer = computed(() => maxText.value != maxDefaultText.value)

      // dialog-methods
      const minmaxCalc = ref(null)
      const openMinCalc = async () => {
        const newMin = await minmaxCalc.value.open({
          title: 'Min-Bestand berechnen',
          message: 'Gib eine Packungsgröße und eine Menge ein, um einen neuen Min-Bestand zu errechnen.',
          sizes: itemForm.sizes,
          curSize: itemForm.sizes.find(e=>e.is_default),
          allowZero: true,
        })
        if (newMin === null) { return }
        itemForm.min_stock = newMin
      }
      const openMaxCalc = async () => {
        const newMax = await minmaxCalc.value.open({
          title: 'Max-Bestand berechnen',
          message: 'Gib eine Packungsgröße und eine Menge ein, um einen neuen Max-Bestand zu errechnen.',
          sizes: itemForm.sizes,
          curSize: itemForm.sizes.find(e=>e.is_default),
          allowZero: true,
        })
        if (newMax === null) { return }
        itemForm.max_stock = newMax
      }

    // #endregion

    // #region editor-quantity/expiry

      // computed
      const isValidExpiry = computed(() => {
        return itemForm.current_expiry !== null && !isNaN(itemForm.current_expiry)
      })

      // props
      const currentExpiryMonth = ref(null)
      const currentExpiryYear = ref(null)
      const selectableStockChangeReasons = ref([
        { name: "Abweichung", value: -1 },
        { name: "Verfall", value: -2 },
        { name: "Beschädigung", value: -3 },
      ])

      // update
      const updateExpiry = () => {
        if (!currentExpiryMonth.value || !currentExpiryYear.value) { 
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

    // #endregion

  // #endregion

  // #region table

    // #region data-table

      const tableheaders = ref([
        { title: 'Name', key: 'name' },
        { title: 'Anforderung', key: 'demand' },
        { title: 'Min', key: 'min_stock' },
        { title: 'Max', key: 'max_stock' },
        { title: 'Bestand', key: 'current_quantity' },
        { title: 'Bestand (inkl.)', key: 'demanded_quantity' },
        { title: 'Verfall', key: 'current_expiry' },
      ])

      const getExpiryText = (dstr) => {
        return new Date(dstr).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }).replace(' ', '-').replace('.', '');
      }

    // #endregion

  // #endregion

// #endregion

// #region touchmode

  const handleEsc = () => {
    if (isItemSelected.value) {
      clearSelectedItem()
    } else {
      openWelcome()
    }
  }
  const handleEnter = () => {
    if (isItemSelected.value && !isEditSizeVisible.value && !minmaxCalc.value.isVisible) {
      saveItem()
    }
  }

  onMounted(() => {
    InputService.registerEsc(handleEsc)
    InputService.registerEnter(handleEnter)
  })
  onUnmounted(() => {
    InputService.unregisterEsc(handleEsc)
    InputService.unregisterEnter(handleEnter)
  })

// #endregion

</script>

<template>

  <Head title="Inventur" />
  <CursorHandler />

  <div class="app-Inventory">

    <LcPagebar title="Inventur" @back="openWelcome">
      <template #actions>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openConfigDemands">Anforderungen
        </v-btn>
        <v-btn v-if="!isItemSelected" variant="flat" 
          @click="openConfigUsages">Verwendungen
        </v-btn>
      </template>
    </LcPagebar>

    <div class="app-Inventory--page">

      <template v-if="!isItemSelected">

        <LcItemInput 
          :result-pos="{ w: 850, i: 14.5 }"
          :admin-mode="true" 
          @create-new="createNew" @select-item="editItem">
        </LcItemInput>

        <!-- DashBoard -->
        <v-card title="Verfall prüfen" class="mt-2" variant="outlined">
          <v-card-text>

            <v-data-table 
              :items="itemsNearExpiry" :headers="tableExpiry" :sort-by="sortExpiry"
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
        <!-- <v-card title="In Bestellung" class="mt-2" variant="outlined">
          <v-card-text>

            <v-data-table 
              :items="itemsOrdered" :headers="tableOrdered" :sort-by="sortOrdered"
              hide-default-footer :items-per-page="100">
              <template v-slot:item.demand="{ item }">
                {{ item.demand.name }}
              </template>
              <template v-slot:item.action="{ item }">
                <v-btn small variant="outlined" @click="editItem(item)">
                  <v-icon icon="mdi-cog"></v-icon>
                </v-btn>
              </template>
            </v-data-table>

          </v-card-text>
        </v-card> -->

      </template>

      <template v-else>

        <v-toolbar flat>
          <v-app-bar-nav-icon
            icon="mdi-arrow-left" :disabled="itemForm.processing"
            @click="clearSelectedItem"></v-app-bar-nav-icon>
          <v-toolbar-title>
            {{ isNewItem ? 'Neuer Artikel' : 'Artikel bearbeiten - ' + itemForm.name }}
          </v-toolbar-title>
        </v-toolbar>

        <v-expansion-panels class="mt-2" flat multiple
          v-model="editActivePanel" :disabled="itemForm.processing">

          <v-expansion-panel class="mt-1" title="Allgemein" color="black" outlined> 
            <v-expansion-panel-text>

              <v-text-field
                v-model="itemForm.name"
                label="Name"
                required
                hide-details
              ></v-text-field>
              <v-alert v-if="!isValidName"
                text="Du musst einen Namen angeben."
                type="error"></v-alert>

              <v-text-field class="mt-2"
                v-model="itemForm.search_altnames"
                label="Alternative Namen"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model="itemForm.search_tags"
                label="Tags"
                hide-details
              ></v-text-field>

              <v-select
                v-model="itemForm.demand_id" class="mt-2"
                :items="demands"
                label="Anforderung"
                item-title="name"
                item-value="id"
                required
                hide-details
              ></v-select>
              <v-alert v-if="!isValidDemand"
                text="Du musst eine Anforderung angeben."
                type="error"></v-alert>
                
            </v-expansion-panel-text>
          </v-expansion-panel>
          
          <v-expansion-panel class="mt-1" title="Wo ist ... ?" color="black"> 
            <v-expansion-panel-text>

              <v-text-field
                v-model="itemForm.location.room"
                label="Raum"
                hide-details
              ></v-text-field>

              <v-text-field
                v-model="itemForm.location.cab"
                label="Schrank"
                hide-details
              ></v-text-field>

              <v-text-field
                v-model="itemForm.location.exact"
                label="Genauer Ort (z.B. Schublade)"
                hide-details
              ></v-text-field>

            </v-expansion-panel-text>
          </v-expansion-panel>
          
          <v-expansion-panel class="mt-1" title="Packungsgrößen" color="black"> 
            <v-expansion-panel-text>

              <v-data-table :items="itemForm.sizes"
                :headers="sizesHeaders" :sort-by="sizesSort"
                hide-default-footer :items-per-page="100">
                <template v-slot:item.action="{ item }">
                  <v-btn small variant="outlined" @click="beginEditSize(item)">
                    <v-icon icon="mdi-cog"></v-icon>
                  </v-btn>
                </template>
              </v-data-table>

              <v-divider></v-divider>

              <v-btn color="primary" variant="outlined" class="mt-4" prepend-icon="mdi-plus"
                @click="beginAddSize()">Hinzufügen</v-btn>

              </v-expansion-panel-text>
          </v-expansion-panel>
          
          <v-expansion-panel class="mt-1" title="Min- & Maxbestand" color="black"> 
            <v-expansion-panel-text>

              <v-form>
                <v-row>
                  <v-col cols="4">
                    <v-btn prepend-icon="mdi-calculator" variant="outlined"
                      @click="openMinCalc">
                      Min-Bestand ändern
                    </v-btn>
                  </v-col>
                  <v-col cols="2" class="app-Inventory--minmax-result">
                    {{ minDefaultText }}
                  </v-col>
                  <v-col cols="2" class="app-Inventory--minmax-result" v-if="minSizesDiffer">
                    bzw. {{ minText }}
                  </v-col>
                </v-row>
                <v-row class="mt-2">
                  <v-col cols="4">
                    <v-btn prepend-icon="mdi-calculator" variant="outlined"
                      @click="openMaxCalc">
                      Max-Bestand ändern
                    </v-btn>
                  </v-col>
                  <v-col cols="2" class="app-Inventory--minmax-result">
                    {{ maxDefaultText }}
                  </v-col>
                  <v-col cols="2" class="app-Inventory--minmax-result" v-if="maxSizesDiffer">
                    bzw. {{ maxText }}
                  </v-col>
                </v-row>
              </v-form>

              
            </v-expansion-panel-text>
          </v-expansion-panel>
          
          <v-expansion-panel class="mt-1" title="Aktueller Bestand" color="black"> 
            <v-expansion-panel-text>

              <v-form>
                <v-row>
                  <v-col cols="4" class="app-Inventory--table-result text-right">
                    Aktueller Bestand ({{ baseUnit }})
                  </v-col>
                  <v-col cols="5">
                    <v-number-input
                      v-model="itemForm.current_quantity"
                      :reverse="false"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="0"
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
                  <v-col cols="4" class="app-Inventory--table-result">
                    Nächster Verfall
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryMonth"
                      :reverse="false"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="1"
                      :max="12"
                    ></v-number-input>
                    <!-- <v-select 
                      v-model="currentExpiryMonth"
                      :items="selectableMonths"
                      label="Monat"
                      item-title="name"
                      item-value="value"
                      required
                      hide-details
                    ></v-select>  -->
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryYear"
                      :reverse="false"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="(new Date()).getFullYear() - 1"
                      :max="(new Date()).getFullYear() + 99"
                    ></v-number-input>
                  </v-col>
                </v-row>
                <v-row class="mt-n5">
                  <v-col cols="4"></v-col>
                  <v-col cols="5">
                    <v-alert v-if="!isValidExpiry"
                      text="Wann ist der nächste Verfall?"
                      type="error"></v-alert>
                  </v-col>
                </v-row>
              </v-form>

              
            </v-expansion-panel-text>
          </v-expansion-panel>

        </v-expansion-panels>

        <v-card class="mt-2 rounded-0" variant="outlined" :disabled="itemForm.processing">
          <v-card-text class="pa-4">

            <v-form>
              <v-row>
                <v-col cols="3" v-if="!isNewItem">
                  <v-btn color="error" variant="flat" block v-if="!isNewItem"
                    @click="deleteItem">
                    Löschen
                  </v-btn>
                </v-col>
                <v-col :cols="isNewItem ? 12 : 9">
                  <v-btn color="success" variant="flat" block :disabled="!isValidItem" :loading="itemForm.processing"
                    @click="saveItem">
                    Speichern
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>

          </v-card-text>
        </v-card>

      </template>

      <!-- Dialog: PackageSize -->
      <v-dialog v-model="isEditSizeVisible" max-width="420px">
        <v-card prepend-icon="mdi-package-variant-closed" v-if="currentEditSizeItem" class="rounded-0"
          :title="isEditSizeNew ? 'Neue Packungsgröße' : 'Packungsgröße bearbeiten'">

          <v-divider></v-divider>

          <v-card-text>
            <p class="mb-4">
              Gib eine Packungsgröße an.
            </p>

            <v-text-field
              v-model="currentEditSizeItem.amount" :disabled="currentEditSizeItem.defaultOne"
              label="Menge" type="number" :min="2" hide-details>
            </v-text-field>

            <v-text-field class="mt-2"
              v-model="currentEditSizeItem.unit" id="id-editsize-unit"
              label="Größenangabe" hide-details>
            </v-text-field>

            <v-checkbox 
              label="In dieser Packungseinheit bestellen"
              v-model="currentEditSizeItem.is_default"
              hide-details>
            </v-checkbox>

            <v-alert class="mt-2" v-if="isEditSizeUnitExisting"
              text="Diese Einheit existiert bereits." type="error"></v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeAmountExisting"
              text="Diese Menge exisitert bereits." type="error"></v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeEmpty"
              text="Du musst alles ausfüllen." type="error"></v-alert>
            <v-alert class="mt-2" v-else-if="isEditSizeTooLow"
              text="Du kannst nicht unter die Standardgröße." type="error"></v-alert>

          </v-card-text>

          <v-divider></v-divider>

          <v-card-actions class="mx-4 mb-2">
            <v-btn color="error" variant="tonal" v-if="!isEditSizeNew && !isEditSizeDefault"
              @click="deleteEditSize">Delete</v-btn>
            <v-spacer></v-spacer>
            <v-btn @click="cancelEditSize">Abbrechen</v-btn>
            <v-btn color="primary" variant="tonal" :disabled="!isValidSizeEdit"
              @click="acceptEditSize">Speichern</v-btn>
          </v-card-actions>

        </v-card>
      </v-dialog>

      <!-- Dialog: AmountInput -->
      <LcCalcMinMaxDialog ref="minmaxCalc" />

    </div>

  </div>

</template>
<style lang="scss" scoped>
.app-Inventory {

  &--page {
    max-width: 850px;
    margin: .5rem auto;
  }

  &--table-result,
  &--minmax-result {
    display: flex;
    font-weight: bold;
    align-items: center;
    justify-content: right;
  }

  &--minmax-result {
    justify-content: center;
  }

}
</style>
