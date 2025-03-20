<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-sm">
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Ehrenmitglieder</h1>

        <p class="mb-8 text-gray-700 dark:text-gray-300">
            Wir sind stolz darauf, herausragende Autoren der Maddrax-Serie zu unseren Ehrenmitgliedern zählen zu dürfen.
            Diese talentierten Schriftsteller haben maßgeblich zum Erfolg und zur Entwicklung des Maddraxiversums
            beigetragen.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Michael Edelbrock -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <!-- Angepasstes Seitenverhältnis für Portraitfotos -->
                    <img src="{{ asset('images/ehrenmitglieder/michael-edelbrock.jpg') }}" alt="Michael Edelbrock"
                        class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Michael Edelbrock</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Michael Edelbrock wurde 1980 geboren und beschäftigt sich am liebsten mit dicken Schmökern oder
                        langen Sagen, sowohl in der klassischen Phantastik als auch in der Science-Fiction.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Heute lebt er am Rande des Ruhrgebiets und schreibt dort seine Kurzgeschichten, Heftromane sowie
                        eine phantastische Saga in Romanform.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Er schreibt seit 2022 für die Maddrax-Serie und erhielt bisher zweimal den Leserpreis für den
                        besten Roman eines Jahres, die „Goldene Taratze" im Jahr 2022 für <em>Erschütterungen</em> (MX
                        594) und 2025 für <em>Die Gestade der Zeit</em> (MX 628).
                    </p>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 4 von 5 Kometen (basierend auf 4,09 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">4,09 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>

            <!-- Ian Rolf Hill -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/ehrenmitglieder/ian-rolf-hill.jpg') }}" alt="Ian Rolf Hill"
                        class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Ian Rolf Hill</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Ian Rolf Hill (Florian Hilleberg) wurde 1980 geboren und ist seit 2016 für Maddrax aktiv. Für
                        die Serie hat er eine Vielzahl an Romanen geschrieben und interessante Charaktere entwickelt,
                        und hat so die Weiterentwicklung des Maddrax-Universums aktiv mitgestaltet.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Er schreibt außerdem für John Sinclair. Seit 2024 hat er beschlossen, als Maddrax-Autor kürzer
                        zu treten.
                    </p><br><br><br>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 3,5 von 5 Kometen (basierend auf 3,47 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500 opacity-50" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">3,47 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>

            <!-- Lucy Guth -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/ehrenmitglieder/lucy-guth.jpg') }}" alt="Lucy Guth"
                        class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Lucy Guth</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Lucy Guth (Tanja Monique Bruske-Guth) wurde 1978 geboren und ist seit 2014 als Maddrax-Autorin
                        dabei und hat dementsprechend schon viel Gutes beigetragen. In ihrem Hauptberuf arbeitet sie als
                        Redakteurin bei der Gelnhäuser Neuen Zeitung.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Sie veröffentlicht als Tanja Bruske Theaterstücke und Romane und als Lucy Guth auch für Perry
                        Rhodan. 2023 erhielt sie den Leserpreis „Goldene Taratze" für ihren Roman <em>Das Haus auf dem
                            Hügel</em> (MX 607).
                    </p><br>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 3,5 von 5 Kometen (basierend auf 3,69 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500 opacity-70" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">3,69 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>

            <!-- Oliver Müller -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/ehrenmitglieder/oliver-mueller.jpg') }}" alt="Oliver Müller"
                        class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Oliver Müller</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Oliver Müller wurde 1983 geboren und gab seinen Einstand bei Maddrax im Jahr 2014 mit <em>Ein
                            Käfig aus Zeit</em> (MX 365). Neben seinem Hauptberuf veröffentlicht er viele
                        Kurzgeschichten und schreibt Romane, auch für Professor Zamorra und John Sinclair.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Sein Roman <em>Der Giftplanet</em> (MX 540) wurde 2021 zum besten Maddrax-Roman mit der
                        „Goldenen Taratze" ausgezeichnet.
                    </p><br><br>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 3,5 von 5 Kometen (basierend auf 3,60 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500 opacity-60" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">3,60 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>

            <!-- Michael Schönenbröcher -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/ehrenmitglieder/michael-schoenenbröcher.jpg') }}"
                        alt="Michael Schönenbröcher" class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Michael Schönenbröcher</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Michael Schönenbröcher (Mad Mike) wurde 1961 geboren, ist seit 1979 Lektor beim Bastei Verlag
                        und seit 2000 alleiniger Betreuer von Maddrax. Die Serie, die in Zusammenarbeit mit den Autoren
                        immer weiter ausgestaltet wird, geht auf seine Idee zurück.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Neben der redaktionellen Arbeit schrieb er in der Vergangenheit auch selbst Romane für Maddrax.
                        Außerdem entwirft er etliche der außergewöhnlichen Cover - oder lässt sie von Künstlern speziell
                        anfertigen.
                    </p>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 3,5 von 5 Kometen (basierend auf 3,78 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500 opacity-80" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">3,78 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>

            <!-- Jo Zybell -->
            <div
                class="bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                <div class="h-80 bg-gray-200 dark:bg-gray-600 flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/ehrenmitglieder/jo-zybell.jpg') }}" alt="Jo Zybell"
                        class="object-cover h-full">
                </div>
                <div class="p-4">
                    <h2 class="text-xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-2">Jo Zybell</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Jo Zybell (Thomas Ziebula) wurde 1954 geboren und hat die Serie als Autor seit 2000 aktiv
                        mitgestaltet. Von ihm wurde eine Vielzahl an Heftromanen und ergänzenden Hardcover-Bücher
                        geschrieben, die das Maddrax-Universum ausloten. 2018 hat er leider das Autorenteam verlassen.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">
                        Er schrieb außerdem unter Pseudonym an verschiedenen Serien mit und hat mehrere Bücher verfasst.
                        2001 gewann er den Deutschen Phantastik-Preis als Bester Autor.
                    </p>
                    <div class="flex items-center mt-4">
                        <div class="flex space-x-1">
                            <!-- 4 von 5 Kometen (basierend auf 3,86 Kometen) -->
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500" viewBox="0 0 426.003 426.003" fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-yellow-500 opacity-85" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-500" viewBox="0 0 426.003 426.003"
                                fill="currentColor">
                                <path
                                    d="M426.003,147.283c0-75.259-61.001-136.254-136.249-136.254c-69.853,0-127.327,52.6-135.232,120.327L8.809,255.267 c-10.521,8.956-11.8,24.744-2.854,35.276c8.956,10.521,24.737,11.806,35.259,2.86L161.015,191.51 c3.935,11.42,9.208,22.21,15.869,32.042L8.809,366.48c-10.521,8.967-11.8,24.743-2.854,35.264 c8.944,10.533,24.737,11.807,35.259,2.85l170.982-145.402c10.965,7.613,23.062,13.686,36.036,17.818l-110.39,93.873 c-10.533,8.967-11.812,24.755-2.855,35.275c8.957,10.533,24.738,11.807,35.259,2.861l152.521-129.697 C382.039,264.539,426.003,211.098,426.003,147.283z">
                                </path>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">3,86 Kometen im Durchschnitt</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-gray-200 dark:bg-gray-700 p-4 rounded-lg">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                Die Bewertungen der Romane werden durch die Community des <a href="https://de.maddraxikon.com"
                    target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Maddraxikons</a> erstellt.
                Die „Kometen" entsprechen dabei einer Bewertung von 1-5 Sternen, wobei 5 Kometen die bestmögliche
                Bewertung darstellt.
            </p>
        </div>
    </div>
</x-app-layout>