<div class="space-y-6">
    <h1>Belohnungen - Admin</h1>

    <section>
        <h2>Belohnungen</h2>
        <p>Mitgliederkarte</p>
        <p>{{ $this->mitgliederkarteReward->cost_baxx }}</p>

        @foreach($this->rewards as $reward)
            <div>{{ $reward->title }}</div>
        @endforeach
    </section>

    @if($activeTab === 'statistics')
        <section>
            <h2>Statistiken</h2>
            <p>{{ $this->statistics['total_spent_baxx'] }}</p>

            @foreach($this->statistics['rewards_stats'] as $reward)
                <div>{{ $reward->title }}</div>
            @endforeach
        </section>
    @endif

    @if($activeTab === 'downloads')
        <section>
            <h2>Downloads</h2>

            @foreach($this->downloads as $download)
                <div>{{ $download->title }}</div>
            @endforeach
        </section>
    @endif

    @if($activeTab === 'purchases')
        <section>
            <h2>Freischaltungen</h2>

            @foreach($this->purchases as $purchase)
                <div>{{ $purchase->reward?->title }}</div>
            @endforeach
        </section>
    @endif

    @if($activeTab === 'rules')
        <section>
            <h2>Vergaberegeln</h2>

            @foreach($this->earningRules as $rule)
                <div>{{ $rule->label }}</div>
            @endforeach

            @foreach($this->romantauschRewardConfiguration as $configuration)
                <div>{{ $configuration['action_label'] }}</div>
                <div>{{ $configuration['effective_rule']['rule_label'] }}</div>
            @endforeach

            @foreach($this->romantauschSpecialOffers as $offer)
                <div>{{ \App\Models\RomantauschBaxxSpecialOffer::actionLabel($offer->action_key) }}</div>
            @endforeach

            @foreach($this->reviewSpecialOffers as $offer)
                <div>{{ $offer->points }}</div>
            @endforeach
        </section>
    @endif
</div>