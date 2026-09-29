import { defineStore } from 'pinia';

export const useImeiWizardStore = defineStore('imeiWizard', {
    state: () => ({
        currentStep: 1,
        form: {
            sim_type: 'single', // single atau dual
            imei1: '',
            imei2: '',
            package_id: null,
            voucher_code: '',
        },
        deviceDetails: null, // Menyimpan response verifikasi CEIRKU
        selectedPackage: null,
    }),
    actions: {
        setStep(step) {
            this.currentStep = step;
        },
        nextStep() {
            this.currentStep++;
        },
        prevStep() {
            this.currentStep--;
        },
        resetWizard() {
            this.$reset();
        }
    },
    persist: true // Mengaktifkan localstorage persistence jika pinia-plugin-persistedstate terinstall
});