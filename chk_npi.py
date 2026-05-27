import subprocess
r = subprocess.run(['mysql','-upduser','-pYm@thsky12101992','paperlessmd','-e','DESCRIBE form_submissions;'], capture_output=True, text=True)
for l in r.stdout.splitlines():
    if 'provider' in l.lower() or 'npi' in l.lower():
        print(l)
