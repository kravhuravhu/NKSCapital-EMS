@props(['title', 'rows' => [], 'contract' => false])

<div class="panel">
    <h2>{{ $title }}</h2>

    @if ($contract)
        <p class="profile-contract-note">Contract details are managed by your organization. You can only view this information</p>
    @endif

    @foreach ($rows as $row)
        <div class="profile-info-row">
            <span class="profile-info-label">{{ $row[0] }}</span>
            <strong class="profile-info-value">{{ $row[1] }}</strong>
        </div>
    @endforeach
</div>