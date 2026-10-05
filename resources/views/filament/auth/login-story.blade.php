<aside class="carbay-login-story" aria-label="Carbay+ sign-in">
    <div class="carbay-story-topline">
        <span class="carbay-story-dot"></span>
        {{ $isSuperAdmin ? 'THE CARBAY+ NETWORK' : 'YOUR BAY. IN SYNC.' }}
    </div>

    <div class="carbay-story-copy">
        <p class="carbay-story-kicker">
            {{ $isSuperAdmin ? 'A clearer view of every bay.' : 'Good work starts here.' }}
        </p>
        <h2>
            {{ $isSuperAdmin ? 'Keep every wash moving.' : 'Run your bay, beautifully.' }}
        </h2>
        <p class="carbay-story-description">
            {{ $isSuperAdmin
                ? 'One calm place to look after the people and businesses powering the network.'
                : 'Sales, your crew and every branch, together in one simple workspace.' }}
        </p>
    </div>

    <div class="carbay-wash-art" aria-hidden="true">
        <span class="carbay-orbit carbay-orbit-one"></span>
        <span class="carbay-orbit carbay-orbit-two"></span>
        <span class="carbay-art-spark carbay-art-spark-one">✦</span>
        <span class="carbay-art-spark carbay-art-spark-two">✧</span>
        <svg class="carbay-car-illustration" viewBox="0 0 420 220" fill="none">
            <ellipse cx="213" cy="185" rx="145" ry="15" fill="#071C18" opacity=".35"/>
            <path d="m86 140 20-53c5-13 17-22 31-24l35-4 34-37c8-9 19-14 31-14h59c13 0 25 7 32 18l31 45 25 10c15 6 25 21 25 37v27c0 12-9 21-21 21h-17a39 39 0 0 0-76 0h-99a39 39 0 0 0-76 0H91c-10 0-17-8-17-18v-3c0-6 5-11 12-11Z" fill="url(#carbody)"/>
            <path d="m153 70 27-3 32-35c5-5 11-8 18-8h30v46H153Zm84-46h58c8 0 15 4 20 11l25 35H237V24Z" fill="#DDF2DF" opacity=".95"/>
            <path d="m180 67 29-32c5-5 11-8 18-8h6v40h-53Zm57-40h58c8 0 15 4 20 11l21 29H237V27Z" fill="#B8E0C5"/>
            <path d="m210 32-27 35m54-42v42" stroke="#165247" stroke-width="5" stroke-linecap="round"/>
            <path d="m105 91-11 34m302-1h-34" stroke="#F7D578" stroke-width="7" stroke-linecap="round"/>
            <circle cx="145" cy="164" r="29" fill="#10342D" stroke="#E8F2E8" stroke-width="8"/>
            <circle cx="145" cy="164" r="10" fill="#F7D578"/>
            <circle cx="324" cy="164" r="29" fill="#10342D" stroke="#E8F2E8" stroke-width="8"/>
            <circle cx="324" cy="164" r="10" fill="#F7D578"/>
            <path d="M44 76c11-10 18-9 27 0m-21 11c8-7 13-6 19 0m-14 10c5-4 8-3 11 0" stroke="#D7F1DF" stroke-width="4" stroke-linecap="round" opacity=".8"/>
            <path d="M356 42c8-8 16-8 24 0m-18 10c5-5 10-5 15 0" stroke="#D7F1DF" stroke-width="4" stroke-linecap="round" opacity=".8"/>
            <defs>
                <linearGradient id="carbody" x1="91" y1="44" x2="354" y2="188" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#F7D578"/>
                    <stop offset="1" stop-color="#EBAF46"/>
                </linearGradient>
            </defs>
        </svg>
        <div class="carbay-art-caption">
            <span class="carbay-caption-check">✓</span>
            {{ $isSuperAdmin ? 'A network in good hands' : 'Your team is ready when you are' }}
        </div>
    </div>

    <div class="carbay-story-footer">
        <span>Made for the people behind the clean.</span>
        <span class="carbay-footer-plus">CARBAY<span>+</span></span>
    </div>
</aside>
