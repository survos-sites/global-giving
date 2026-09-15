import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['shared'];

    toggleShared(event) {
        for (const result of this.sharedTargets) {
            result.hidden = event.currentTarget.checked;
        }
    }
}
