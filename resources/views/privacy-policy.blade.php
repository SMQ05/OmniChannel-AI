<x-guest-layout>
    <div class="sm:max-w-3xl w-full mx-auto p-8 rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 my-10">
        <h1 class="text-3xl font-bold text-white mb-6">Privacy Policy</h1>
        
        <div class="prose prose-invert max-w-none text-gray-300">
            <p class="mb-4">Last updated: {{ now()->format('Y-m-d') }}</p>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">1. Introduction</h2>
            <p class="mb-4">Welcome to our application. We respect your privacy and are committed to protecting your personal data.</p>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">2. Data We Collect</h2>
            <p class="mb-4">We merely facilitate WhatsApp messaging for our clients. Data processed includes:</p>
            <ul class="list-disc pl-5 mb-4 space-y-2">
                <li>Phone numbers (to reply to messages you send)</li>
                <li>Message contents (processed by AI to draft responses and schedule bookings)</li>
            </ul>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">3. How We Use Your Data</h2>
            <p class="mb-4">Information is solely used for the operation of the conversational agent to reply to your queries and manage your appointments with the business you are communicating with.</p>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">4. Third-Party Services</h2>
            <p class="mb-4">We utilize Meta's WhatsApp Cloud API to send and receive messages. Your data interactions are strictly governed by Meta's standards and policies.</p>

            <h2 class="text-xl font-semibold text-white mt-8 mb-4">5. Contact Us</h2>
            <p class="mb-4">If you have any questions or concerns about this privacy policy, please contact the business operating this chat directly.</p>
        </div>
    </div>
</x-guest-layout>
