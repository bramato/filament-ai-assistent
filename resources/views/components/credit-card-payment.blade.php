<div>
    @push('styles')
        <script src="https://js.stripe.com/v3/"></script>
    @endpush
    <livewire:list-user-cards></livewire:list-user-cards>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Registra una nuovo metodo di pagamento
            </x-slot>

            <div x-data="{
    stripe: null,
    card: null,
    setupElements: function () {
        this.stripe = Stripe('{{ config('cashier.key') }}');
        const elements = this.stripe.elements();
        this.card = elements.create('card');
        this.card.mount('#card-element');
    },
submitPaymentMethod: function () {
    this.stripe.createPaymentMethod('card', this.card, {
        billing_details: { name: '{{\Illuminate\Support\Facades\Auth::user()->name}}' }
    }).then((result) => {
        if (result.error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = result.error.message;
        } else {
            @this.set('paymentMethodId', result.paymentMethod.id);
            @this.call('submitPaymentMethod');
            // Reinizializza gli elementi Stripe
            this.setupElements();
            // Ripristina lo stato del form
            document.getElementById('card-errors').textContent = '';
            // Puoi anche reimpostare il form su uno stato iniziale se necessario
        }
    });
}
}" x-init="setupElements">

                <form x-on:submit.prevent="submitPaymentMethod">
                    <div id="card-element"></div>
                    <div class="pt-5">
                        <x-filament::button class="mt-6" type="submit">
                            Registra Metodo di Pagamento
                        </x-filament::button>
                    </div>

                </form>
                <div id="card-errors" role="alert"></div>
            </div>
        </x-filament::section>



</div>
