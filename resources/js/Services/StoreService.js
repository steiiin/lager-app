/**
 * StoreService - data storage
 *
 * This Service contains app-wide data storage via pinia.
 * Contains items and usages, so it only loads on app-start once.
 *
 */

import { defineStore } from 'pinia'
import axios from 'axios';

const tagCommata = (text, lowercase = false) => {
  return (lowercase ? text?.toLowerCase() : text)?.trim().replace(/^,|,$/g, '').split(',').map(tag => tag.trim()).filter(tag => tag !== '') ?? []
}

export const useInventoryStore = defineStore('inventory', {
  state: () => ({
    items: [],
    usages: [],
    isLoaded: false,
    loading: false,
    error: null,
  }),
  getters: {
    searchableItems: (state) => {
      return state.items.map(item => ({
        ...item,
        pp_name: item.name.toLowerCase(),
        pp_altnames_list: tagCommata(item.search_altnames),
        pp_tags_list: tagCommata(item.search_tags),
        pp_search_altnames: item.search_altnames ? item.search_altnames.toLowerCase() : '',
        pp_search_altnames_list: tagCommata(item.search_altnames, true),
        pp_search_tags: item.search_tags ? item.search_tags.toLowerCase() : '',
        pp_search_tags_list: tagCommata(item.search_tags, true),
      }))
    },
    findItemByBarcode: (state) => {
      return (barcode) => {
        const foundItem = state.items.find((item) => item.barcodes.hasOwnProperty(barcode)) ?? null
        if (!foundItem) { return foundItem }
        return {
          item: foundItem,
          amount: foundItem.barcodes[barcode]
        }
      }
    }
  },
  actions: {

    async fetchStore(forInventory = false) {
      if ((!forInventory && this.isLoaded) || this.loading) {
        return;
      }
      this.loading = true;
      try
      {
        const response = await axios.get('/api/store' + (forInventory ? '-inventory' : ''));
        this.items = response.data.items
        this.usages = response.data.usages
        this.isLoaded = true
      }
      catch (err)
      {
        this.error = err;
        console.error('Failed to fetch items:', err);
      }
      finally
      {
        this.loading = false;
      }
    },

  },
});
