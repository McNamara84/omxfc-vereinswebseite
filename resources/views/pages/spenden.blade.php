<x-app-layout title="Spenden – Offizieller MADDRAX Fanclub e. V." description="Unterstütze unseren Fanclub finanziell für Fantreffen, Projekte und Serverkosten.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Spenden</h1>
        <p class="mb-4">Der Offizielle MADDRAX Fanclub e. V. bietet Fans der postapokalyptischen Genre-Mix-Serie MADDRAX eine Plattform zum Austausch und zur gemeinsamen Organisation.</p>
        <p class="mb-6">Spenden helfen uns bei der Finanzierung der jährlichen Fantreffen sowie der Serverkosten dieser Webseite.</p>
        <form action="https://www.paypal.com/donate" method="post" target="_top" class="mt-4 text-center">
            <input type="hidden" name="business" value="kassenwart@maddrax-fanclub.de" />
            <input type="hidden" name="no_recurring" value="0" />
            <input type="hidden" name="currency_code" value="EUR" />
            <button type="submit" class="bg-[#0070ba] hover:bg-[#005c99] text-white font-semibold py-2 px-4 rounded">
                Spenden mit PayPal
            </button>
        </form>
    </x-public-page>
</x-app-layout>
