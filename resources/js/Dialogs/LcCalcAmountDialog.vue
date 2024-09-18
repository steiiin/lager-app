<script setup>

// #region imports

  // Vue composables
  import { ref, computed } from 'vue'

  // Vuetify components
  import { VNumberInput } from 'vuetify/labs/VNumberInput'

// #endregion

// #region dialog

  // props
  const isVisible = ref(false)
  let resolvePromise

  // methods
  const open = async (opts) => {

    currentItem.value = opts.item
    currentSize.value = opts.item.basesize
    currentAmount.value = 1

    isVisible.value = true
    return new Promise((resolve) => {
      resolvePromise = resolve
    })

  }
  const cancel = () => {
    isVisible.value = false
    resolvePromise(null)
  }
  const accept = () => {
    isVisible.value = false
    resolvePromise(currentCalculation.value)
  }

// #endregion

// #region item

  const currentItem = ref(null)

// #endregion

// #region Calculation

  const sizesList = computed(() => !currentItem.value ? [] : currentItem.value.sizes)
  const currentBaseSize = computed(() => !currentItem.value ? null : currentItem.value.basesize.unit)

  const currentSize = ref(null)
  const currentAmount = ref(0)
  const isValidAmount = computed(() => currentAmount.value > 0)

  const currentCalculation = computed(() => isValidAmount ? Math.round(currentAmount.value * currentSize.value.amount) : 0)

  const currentMin = computed(() => (currentSize.value.amount%2 === 0) ? 0.5 : 1)

// #endregion

// #region expose

  defineExpose({ open, isVisible })

// #endregion

</script>

<template>
  <v-dialog v-model="isVisible" max-width="850px" @after-leave="cancel">
    <v-card prepend-icon="mdi-basket" :title="currentItem.name" class="rounded-0">
 
      <v-divider></v-divider>

      <v-card-text class="lc-calcamount--card">

        <v-form>
          <v-row>
            <v-col cols="6">

              <v-chip label class="mb-2">Hier findest du es:</v-chip>
              
              <div class="lc-calcamount--location">
                <div class="lc-calcamount--location-coarse">
                  <v-icon icon="mdi-domain"></v-icon>
                  {{ currentItem.location.room }}
                </div>
                <div class="lc-calcamount--location-cab mt-2" v-if="!!currentItem.location.cab">
                  <v-icon icon="mdi-fridge"></v-icon> 
                  {{ currentItem.location.cab }}
                </div>
                <div class="lc-calcamount--location-exact mt-2" v-if="!!currentItem.location.cab">
                  <v-icon icon="mdi-archive-marker-outline"></v-icon>
                  {{ currentItem.location.exact }}
                </div>
              </div>

            </v-col>
            <v-divider vertical></v-divider>
            <v-col cols="6">

              <div class="mb-2">
                <v-chip color="primary" label>Gib die Menge an, die du ausbuchen möchtest:</v-chip>
              </div>

              <v-number-input
                v-model="currentAmount"
                :reverse="false"
                controlVariant="split"
                :hideInput="false"
                :inset="false" hide-details
                :min="currentMin"
              ></v-number-input>

              <v-select
                v-model="currentSize"
                :items="sizesList"
                label="Größen"
                item-title="unit"
                return-object
                required
                hide-details
              ></v-select>

              <v-alert v-if="isValidAmount && currentSize.amount>1" :text="currentCalculation + ' ' + currentBaseSize" class="mt-4"></v-alert>

            </v-col>
          </v-row>
        </v-form>
    
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="mx-4 mb-2">
        <v-btn @click="cancel">Abbrechen</v-btn>
        <v-btn color="primary"
          variant="tonal" :disabled="!isValidAmount"
          @click="accept">Übernehmen</v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>
</template>
<style lang="scss">

.lc-calcamount {
  
  &--location {
    &-coarse {
      font-size: 1.2rem;
      display: flex;
      gap: 0.5rem;
    }
    &-cab {
      font-size: 1.0rem;
      display: flex;
      gap: 0.5rem;
      margin-left: 1rem;
    }
    &-exact {
      font-size: 1.0rem;
      display: flex;
      gap: 0.5rem;
      margin-left: 2rem;
    }
  }

  &--card {
    padding: calc(16px + .5rem) !important;
  }

}
</style>