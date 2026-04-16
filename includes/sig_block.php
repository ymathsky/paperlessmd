<!-- Signature Block — included inside forms -->
<div class="bg-white border-2 border-blue-100 rounded-2xl overflow-hidden mt-6">
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-3 flex items-center gap-2">
        <i class="bi bi-pen-fill text-white"></i>
        <span class="text-white font-semibold text-sm">Patient / Authorized Representative Signature</span>
    </div>
    <div class="p-5">
        <!-- POA toggle -->
        <label class="flex items-center gap-3 cursor-pointer mb-4 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-blue-300 transition-colors">
            <input type="checkbox" id="poaCheck" class="big">
            <div>
                <div class="font-semibold text-sm text-slate-700">Signing as Power of Attorney / Legal Guardian</div>
                <div class="text-xs text-slate-500 mt-0.5">Check if signer is an authorized representative, not the patient</div>
            </div>
        </label>

        <!-- POA fields -->
        <div id="poaFields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Representative Full Name<span class="text-red-500 ml-0.5">*</span></label>
                <input type="text" name="poa_name"
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                       placeholder="Full legal name">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Relationship to Patient<span class="text-red-500 ml-0.5">*</span></label>
                <input type="text" name="poa_relationship"
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                       placeholder="e.g. Spouse, Child, Legal Guardian">
            </div>
        </div>

        <!-- Alert -->
        <div id="sigAlert" class="hidden flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4">
            <i class="bi bi-exclamation-circle text-lg flex-shrink-0"></i>
            Please provide a signature before submitting.
        </div>

        <!-- Canvas -->
        <label class="block text-sm font-semibold text-slate-700 mb-2">Sign below
            <span class="text-slate-400 font-normal text-xs ml-1">(use finger or stylus on tablet)</span>
        </label>
        <div class="sig-wrapper border-2 border-dashed border-slate-300 rounded-2xl focus-within:border-blue-400 transition-colors">
            <canvas id="signaturePad"></canvas>
            <div class="sig-placeholder">Sign here</div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button type="button" id="clearSig"
                    class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl transition-colors">
                <i class="bi bi-eraser"></i> Clear
            </button>
            <span class="text-xs text-slate-400">Your signature confirms agreement to the information above</span>
        </div>
        <input type="hidden" name="patient_signature" id="sigData">
    </div>
</div>

<!-- MA Signature Block -->
<div class="bg-white border-2 border-indigo-100 rounded-2xl overflow-hidden mt-4">
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-5 py-3 flex items-center gap-2">
        <i class="bi bi-person-badge-fill text-white"></i>
        <span class="text-white font-semibold text-sm">Medical Assistant Signature</span>
    </div>
    <div class="p-5">
        <div id="maSigAlert" class="hidden flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4">
            <i class="bi bi-exclamation-circle text-lg flex-shrink-0"></i>
            MA signature is required before submitting.
        </div>
        <label class="block text-sm font-semibold text-slate-700 mb-2">MA sign below
            <span class="text-slate-400 font-normal text-xs ml-1">(staff member completing this form)</span>
        </label>
        <div class="sig-wrapper border-2 border-dashed border-slate-300 rounded-2xl focus-within:border-indigo-400 transition-colors" id="maSigWrapper">
            <canvas id="maSigPad"></canvas>
            <div class="sig-placeholder">MA sign here</div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button type="button" id="clearMaSig"
                    class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl transition-colors">
                <i class="bi bi-eraser"></i> Clear
            </button>
            <span class="text-xs text-slate-400">Confirms accuracy of information recorded</span>
        </div>
        <input type="hidden" name="ma_signature" id="maSigData">
    </div>
</div>
