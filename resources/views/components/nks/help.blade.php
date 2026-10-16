@props(['documents' => false])

<div class="panel">
    <h2>Need Help?</h2>

    <div class="help-body">
        <i class="fa-solid fa-headset"></i>
        <div>
            <p>
                @if ($documents)
                    Can't find the document you need? Contact HR and we will assist you.
                @else
                    Report an issue with your asset or request an assistance from IT.
                @endif
            </p>
            <button type="button" class="nks-button nks-button-outline">
                {{ $documents ? 'Contact HR' : 'Request Support' }}
            </button>
        </div>
    </div>
</div>