import subprocess

r = subprocess.run(
    ['mysql', '-upduser', '-pYm@thsky12101992', 'paperlessmd', '-e', 'DESCRIBE form_submissions;'],
    capture_output=True, text=True
)
print(r.stdout)
print(r.stderr[:200] if r.stderr else '')
