<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 bg-gray-100 dark:bg-gray-800">
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Spenden</h1>
        <p class="mb-4">Der Offizielle MADDRAX Fanclub e. V. bietet Fans der Science-Fiction-Serie eine Plattform zum Austausch und zur gemeinsamen Organisation.</p>
        <p class="mb-6">Spenden helfen uns bei der Finanzierung der jährlichen Fantreffen sowie der Serverkosten dieser Webseite.</p>
        <form action="https://www.paypal.com/donate" method="post" target="_top" class="mt-4">
            <input type="hidden" name="business" value="kassenwart@maddrax-fanclub.de" />
            <input type="hidden" name="no_recurring" value="0" />
            <input type="hidden" name="currency_code" value="EUR" />
            <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donateCC_LG.gif" name="submit" alt="Spenden mit PayPal" />
            <img alt="" src="https://www.paypal.com/en_DE/i/scr/pixel.gif" width="1" height="1" />
        </form>
    </div>
</x-app-layout>
