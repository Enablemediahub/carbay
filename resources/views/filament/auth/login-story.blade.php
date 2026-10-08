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
        <img class="carbay-car-illustration" src="{{ asset('carbay-car.png') }}" alt="">
        <div class="carbay-art-caption">
            <span class="carbay-caption-check">✓</span>
            {{ $isSuperAdmin ? 'A network in good hands' : 'Your team is ready when you are' }}
        </div>
    </div>

    <div class="carbay-story-footer">
        <span>Made for the people behind the clean.</span>
        <span class="carbay-footer-plus">CARBAY<span>+</span></span>
    </div>
    @unless ($isSuperAdmin)
        <a href="{{ route('manager.login') }}" style="display: block; margin-top: 12px; color: inherit; text-decoration: underline;">Manager phone &amp; PIN sign in</a>
    @endunless
</aside>
