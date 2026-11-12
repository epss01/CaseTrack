"""Rebuild the Stage 3 manuscript figures with Pillow; SVGs remain editable.

Run with the bundled Python runtime. No application or database access.
"""
from pathlib import Path
from html import escape
from PIL import Image, ImageDraw, ImageFont
import math
import xml.etree.ElementTree as ET

OUT = Path(__file__).parent


class Figure:
    def __init__(self, name, width, height):
        self.name, self.width, self.height = name, width, height
        self.svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">', '<rect width="100%" height="100%" fill="white"/>']
        self.image = Image.new('RGB', (width * 2, height * 2), 'white')
        self.draw = ImageDraw.Draw(self.image)

    def text(self, x, y, lines, size=25, bold=False, anchor='middle'):
        font = ImageFont.truetype('C:/Windows/Fonts/arialbd.ttf' if bold else 'C:/Windows/Fonts/arial.ttf', size * 2)
        for i, line in enumerate(lines.split('\n')):
            yy = y + i * (size + 7)
            self.svg.append(f'<text x="{x}" y="{yy}" text-anchor="{anchor}" font-family="Arial" font-size="{size}" font-weight="{"bold" if bold else "normal"}" fill="#111">{escape(line)}</text>')
            self.draw.text((x * 2, yy * 2), line, font=font, fill='#111', anchor='ms' if anchor == 'middle' else 'ls')

    def box(self, x, y, w, h, lines, size=25, ellipse=False, dashed=False):
        dash = ' stroke-dasharray="9 6"' if dashed else ''
        if ellipse:
            self.svg.append(f'<ellipse cx="{x+w/2}" cy="{y+h/2}" rx="{w/2}" ry="{h/2}" fill="#f6f6f6" stroke="#222" stroke-width="2"/>')
            self.draw.ellipse((x*2,y*2,(x+w)*2,(y+h)*2),fill='#f6f6f6',outline='#222',width=4)
        else:
            self.svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="4" fill="#f6f6f6" stroke="#222" stroke-width="2"{dash}/>')
            self.draw.rectangle((x*2,y*2,(x+w)*2,(y+h)*2),fill='#f6f6f6',outline='#222',width=4)
        n = len(lines.split('\n'))
        self.text(x+w/2, y+h/2-(n-1)*(size+7)/2+size*.32, lines, size)

    def line(self, points, arrow=True, dashed=False):
        p = ' '.join(f'{x},{y}' for x,y in points)
        self.svg.append(f'<polyline points="{p}" fill="none" stroke="#222" stroke-width="2"'+(' stroke-dasharray="9 6"' if dashed else '')+'/>')
        self.draw.line([(x*2,y*2) for x,y in points],fill='#222',width=4)
        if arrow:
            (a,b),(x,y)=points[-2:]
            angle=math.atan2(y-b,x-a)
            tip=[(x,y),(x-13*math.cos(angle-.45),y-13*math.sin(angle-.45)),(x-13*math.cos(angle+.45),y-13*math.sin(angle+.45))]
            self.svg.append('<polygon points="'+' '.join(f'{a},{b}' for a,b in tip)+'" fill="#222"/>')
            self.draw.polygon([(a*2,b*2) for a,b in tip],fill='#222')

    def save(self):
        self.svg.append('</svg>')
        data='\n'.join(self.svg)
        ET.fromstring(data)
        (OUT/(self.name+'.svg')).write_text(data,encoding='utf-8')
        self.image.save(OUT/(self.name+'.png'))

    def actor(self, x, y, label):
        self.svg.append(f'<circle cx="{x}" cy="{y}" r="15" fill="white" stroke="#222" stroke-width="2"/>')
        self.draw.ellipse(((x-15)*2,(y-15)*2,(x+15)*2,(y+15)*2),outline='#222',width=4)
        for points in [[(x,y+15),(x,y+65)],[(x-30,y+35),(x+30,y+35)],[(x,y+65),(x-25,y+100)],[(x,y+65),(x+25,y+100)]]:
            self.line(points,False)
        self.text(x,y+132,label,24)


f=Figure('3-4-context',1200,650)
f.text(600,40,'CaseTrack: system context',30,True)
f.box(40,120,280,95,'Investigator\nAssigned cases')
f.box(40,335,280,95,'Supervisor\nOffice-wide cases')
f.box(470,180,290,240,'0\nCaseTrack\nInternal monitoring',28,ellipse=True)
f.line([(320,145),(410,145),(410,230),(470,230)])
f.text(390,95,'Case / timeline inputs',22)
f.line([(470,280),(365,280),(365,200),(320,200)])
f.text(255,267,'Status, alerts, history, reports',22)
f.line([(320,365),(405,365),(470,365)])
f.text(285,463,'Corrections and grant records;\nassignment / performance inputs',20)
f.line([(470,405),(410,405),(410,420),(320,420)])
f.text(760,490,'Reports, alerts and advisory WCS',21)
f.box(900,220,260,105,'Admin\nAccounts only')
f.line([(900,245),(760,245)])
f.text(960,175,'Account decisions',22)
f.line([(760,305),(900,305)])
f.text(960,365,'Account / registration lists',22)
f.box(45,535,1110,85,'External authority grants an extension outside CaseTrack.\nStaff record the decision and reference; CaseTrack never approves it.',24,dashed=True)
f.save()

f=Figure('3-5-data-flow',1200,800)
f.text(600,40,'CaseTrack: data flow',30,True)
f.box(30,90,320,100,'Investigator / Supervisor\nAuthorized case inputs')
f.box(440,90,340,100,'1.0 Validate role, case\npermission and version',25,ellipse=True)
f.line([(350,140),(440,140)])
f.box(870,90,300,100,'D1 Cases and people\nClassification / version')
f.line([(780,115),(870,115)])
f.line([(870,165),(780,165)])
f.box(440,285,340,110,'2.0 Save timeline and\nexternal-grant record',25,ellipse=True)
f.line([(610,190),(610,285)])
f.text(800,240,'Validated input',22)
f.box(870,290,300,100,'D2 Case timelines\nActuals / stored dates')
f.line([(780,320),(870,320)])
f.line([(870,365),(780,365)])
f.box(40,290,290,100,'D3 Audit logs\nChange / reason / dates')
f.line([(440,340),(330,340)])
f.text(180,245,'Atomic change events',22)
f.box(440,485,340,110,'3.0 Evaluate deadlines\nCaseDeadlineService',25,ellipse=True)
f.line([(610,395),(610,485)])
f.box(40,490,290,100,'4.0 Authorized views\nHistory / reports / alerts',25,ellipse=True)
f.line([(440,540),(330,540)])
f.line([(185,390),(185,490)])
f.line([(40,540),(15,540),(15,140),(30,140)])
f.text(630,665,'Confirmed torture: stored base, otherwise docket +60; grant uses docket +90.\nUnknown: no 60-day evaluation. Requests do not grant time.\n90-day formula is provisional; CHR validation pending.',23)
f.text(600,770,'Admin account management is separate; no Admin case-data flow.',23)
f.save()

f=Figure('3-6-use-case',1200,920)
f.text(600,40,'CaseTrack: use cases',30,True)
f.box(320,80,690,825,'',dashed=True)
f.text(665,115,'Application boundary',25,True)
f.actor(150,150,'Investigator')
f.actor(150,465,'Supervisor')
for y,txt in [(145,'Create / edit case and people'),(255,'Review Unknown classification'),(365,'Record / remove external grant'),(475,'View history, alerts and reports')]:
    f.box(370,y,590,80,txt,24,ellipse=True)
    f.line([(180,185),(315,185),(315,y+40),(370,y+40)],False)
    f.line([(180,500),(340,500),(340,y+40),(370,y+40)],False)
f.box(370,585,590,80,'Close / reopen own assigned case',24,ellipse=True)
f.line([(180,185),(285,185),(285,625),(370,625)],False)
f.box(370,695,590,80,'Reassign / set performance rating',24,ellipse=True)
f.line([(180,500),(305,500),(305,735),(370,735)],False)
f.actor(150,750,'Admin')
f.box(370,820,590,70,'Manage registrations and accounts',24,ellipse=True)
f.line([(180,785),(285,785),(285,855),(370,855)],False)
f.text(1090,180,'All actions\nrequire login',21)
f.text(1090,405,'External\nauthority:\nno application\napproval role',21)
f.save()

f=Figure('3-7-activity',1200,1100)
f.text(600,40,'CaseTrack: protected timeline save',30,True)
f.box(30,90,1140,90,'Outside CaseTrack: external authority decides the extension.\nInvestigator / Supervisor receives the decision and supporting reference.',25,dashed=True)
f.box(310,220,580,85,'Open authorized timeline; retain case version')
f.line([(600,180),(600,220)],dashed=True)
f.box(310,350,580,100,'Enter dates, explicit grant state and note;\nSupervisor also gives an edit reason',25)
f.line([(600,305),(600,350)])
f.box(310,495,580,100,'Submit: lock and reload parent case;\nrecheck permission and version',25)
f.line([(600,450),(600,495)])
f.box(30,635,280,100,'Stale / unauthorized /\ninvalid: reject save',23)
f.line([(310,545),(170,545),(170,635)])
f.text(160,520,'Check fails',22)
f.box(410,635,690,120,'Valid confirmed torture grant: calculate docket +90.\nWithout grant: stored base, otherwise docket +60.\nUnknown / No: no 60-day evaluation.',24)
f.line([(700,595),(700,635)])
f.text(870,620,'Checks pass',22)
f.box(410,800,690,100,'Commit timeline, one version increment and audits\nin one transaction; any failure rolls back',25)
f.line([(755,755),(755,800)])
f.box(410,945,690,80,'Show effective deadline and case-specific history',25)
f.line([(755,900),(755,945)])
f.text(600,1080,'90-day formula is a prototype assumption requiring CHR validation.',24)
f.save()

f=Figure('3-8-erd',1300,1560)
f.text(650,40,'CaseTrack: relational model',32,True)
def entity(x,y,w,title,fields):
    h=65+len(fields)*30
    f.box(x,y,w,h,'')
    f.text(x+w/2,y+35,title,27,True)
    for i,field in enumerate(fields):f.text(x+15,y+72+i*30,field,22,anchor='start')
    return h
entity(30,85,280,'roles',['PK id','role_name','timestamps'])
entity(400,85,440,'users',['PK id; username (unique)','password; first_name; last_name','office_region; is_staff (legacy)','FK role_id; performance_rating','registration_status; is_active','FK approved_by (nullable)','approved_at; remember_token','timestamps'])
entity(900,85,370,'audit_logs',['PK id','FK user_id (nullable)','FK target_user_id (nullable)','FK case_id (nullable)','action_performed; timestamp','notes (nullable text)','Extension events: old/new date','and reference in notes'])
f.line([(310,150),(400,150)],False);f.text(350,125,'1 : *',22)
f.line([(840,200),(900,200)],False);f.text(870,175,'1 : *',22)
entity(400,510,440,'cases',['PK id; docket_no (unique)','case_title; incident_details','source_info; FK investigator_id','status; complexity_weight','is_torture_case (nullable bool)','lock_version (unsigned integer)','deleted_at (soft delete)','timestamps'])
f.line([(620,390),(620,510)],False);f.text(735,465,'1 : 0..* assigned',22)
f.line([(1085,390),(1085,620),(840,620)],False);f.text(1065,460,'* : 0..1 case',22)
entity(30,940,300,'complainants',['PK id; FK case_id','name','timestamps'])
entity(30,1140,300,'victims',['PK id; FK case_id','name; age; status; sector','timestamps'])
entity(30,1340,300,'respondents',['PK id; FK case_id','name; age; status; sector','timestamps'])
for y in [970,1170,1370]:
    f.line([(400,700),(365,700),(365,y),(330,y)],False)
    f.text(365,y-20,'1:0..*',18)
entity(470,940,800,'case_timelines',['PK id; FK case_id (unique)','date_of_docket; date_submission_rop','extension_30_days (initial ROP deadline)','submission_60th_day (base RORP deadline)','extension_60th_days (recorded granted deadline)','date_submission_60th_day_rorp (actual)','date_extension_30_days_requested (actual request)','submission_120th_day (deadline)','date_submission_120th_day_rop (actual)','target_date_fir; date_fir_submitted','date_submitted_to (recipient text); timestamps'])
f.line([(700,815),(700,940)],False);f.text(920,880,'1 : 1; legacy timeline may be absent',22)
f.text(875,1435,'No extension-approval table or approval role.\nGrant notes identify the external decision.\nOnly confirmed torture cases receive 60-day evaluation.',23)
f.save()

assert len(list(OUT.glob('3-*.svg'))) == 5
print('Five editable SVG figures and matching PNGs generated.')
