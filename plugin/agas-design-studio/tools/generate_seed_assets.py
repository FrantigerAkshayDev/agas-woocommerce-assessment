#!/usr/bin/env python3
"""Rebuild the demo canvas JSON and matching PNG thumbnails. Pillow is a build-time tool, not a WordPress dependency."""
import json
import textwrap
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
SEEDS = ROOT / 'assets' / 'seeds'
THUMBS = ROOT / 'assets' / 'thumbs'
def pick_font(bold=False):
    candidates = ([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
    ] if bold else [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/System/Library/Fonts/Supplemental/Arial.ttf',
        '/Library/Fonts/Arial.ttf',
    ])
    for filename in candidates:
        if Path(filename).exists(): return filename
    raise RuntimeError('Set a suitable TrueType font path before regenerating previews.')
FONT_REGULAR = pick_font(False)
FONT_BOLD = pick_font(True)
W, H = 900, 600

PRESETS = {
  'outdoor vinyl banner': [
    {'key':'banner-grand-opening','name':'Grand Opening','category':'Events','style':'frame','bg':'#14375B','accent':'#F8CC59','title':'GRAND OPENING','subtitle':'JOIN THE CELEBRATION'},
    {'key':'banner-flash-sale','name':'Flash Sale','category':'Sale','style':'ribbon','bg':'#9B1C31','accent':'#FFF2D5','title':'FLASH SALE','subtitle':'BIG SAVINGS THIS WEEK'},
    {'key':'banner-music-festival','name':'Music Festival','category':'Events','style':'badge','bg':'#48217A','accent':'#FFBB6B','title':'MUSIC FESTIVAL','subtitle':'LIVE MUSIC  -  ALL DAY'},
    {'key':'banner-now-hiring','name':'Now Hiring','category':'Business','style':'stripe','bg':'#075E53','accent':'#B7F7D0','title':'NOW HIRING','subtitle':'GREAT PEOPLE WANTED'},
    {'key':'banner-coming-soon','name':'Coming Soon','category':'Business','style':'split','bg':'#182840','accent':'#20B7AE','title':'COMING SOON','subtitle':'SOMETHING NEW IS ON ITS WAY'},
    {'key':'banner-fundraiser','name':'Community Fundraiser','category':'Community','style':'corners','bg':'#6F3719','accent':'#FFE3A3','title':'COMMUNITY FUNDRAISER','subtitle':'SUPPORT A GREAT CAUSE'},
  ],
  'custom flag': [
    {'key':'flag-france','name':'France Flag','category':'Country Flags','style':'flag_vertical','colors':['#0055A4','#FFFFFF','#EF4135']},
    {'key':'flag-japan','name':'Japan Flag','category':'Country Flags','style':'flag_disc','colors':['#FFFFFF','#BC002D']},
    {'key':'flag-italy','name':'Italy Flag','category':'Country Flags','style':'flag_vertical','colors':['#009246','#FFFFFF','#CE2B37']},
    {'key':'flag-ukraine','name':'Ukraine Flag','category':'Country Flags','style':'flag_horizontal','colors':['#0057B7','#FFD700']},
    {'key':'flag-company','name':'Company Brand Flag','category':'Business','style':'stripe','bg':'#102C49','accent':'#68E0CC','title':'YOUR BRAND','subtitle':'ADD YOUR COMPANY LOGO'},
    {'key':'flag-event','name':'Event Flag','category':'Events','style':'frame','bg':'#522384','accent':'#FFDC79','title':'WELCOME','subtitle':'CELEBRATE WITH US'},
  ],
  'yard sign': [
    {'key':'sign-open-house','name':'Open House','category':'Real Estate','style':'frame','bg':'#133C55','accent':'#EBCB8B','title':'OPEN HOUSE','subtitle':'TOUR THIS HOME TODAY'},
    {'key':'sign-for-sale','name':'For Sale','category':'Real Estate','style':'ribbon','bg':'#A91E31','accent':'#FFF1E6','title':'FOR SALE','subtitle':'CALL FOR DETAILS'},
    {'key':'sign-sold','name':'Sold','category':'Real Estate','style':'badge','bg':'#14304B','accent':'#F1B74F','title':'SOLD','subtitle':'THANK YOU, NEIGHBORS'},
    {'key':'sign-welcome-home','name':'Welcome Home','category':'Real Estate','style':'corners','bg':'#0F5361','accent':'#B8F1DE','title':'WELCOME HOME','subtitle':'WE ARE GLAD YOU ARE HERE'},
    {'key':'sign-reserved','name':'Reserved Parking','category':'Directions','style':'split','bg':'#153E71','accent':'#E3F2FF','title':'RESERVED','subtitle':'PARKING ONLY'},
    {'key':'sign-entrance','name':'Event Entrance','category':'Events','style':'stripe','bg':'#754A05','accent':'#FFE899','title':'EVENT ENTRANCE','subtitle':'WELCOME - THIS WAY'},
  ],
}

def rect(x, y, w, h, fill, stroke=None, sw=0, radius=0):
    data = {'type':'rect','left':x,'top':y,'width':w,'height':h,'fill':fill,'strokeWidth':sw}
    if stroke:
        data['stroke']=stroke
    if radius:
        data['rx'] = data['ry'] = radius
    return data

def circle(x,y,r,fill):
    return {'type':'circle','left':x,'top':y,'radius':r,'fill':fill}

def textbox(text,x,y,w,size,color='#FFFFFF',align='center',bold=True):
    return {'type':'textbox','text':text,'left':x,'top':y,'width':w,'fontSize':size,
      'fontWeight':'bold' if bold else 'normal','fill':color,'fontFamily':'Arial',
      'textAlign':align,'lineHeight':1.05}

def build_canvas(spec):
    style = spec['style']
    if style == 'flag_vertical':
        colors=spec['colors']; objects=[rect(i*300,0,300,600,c) for i,c in enumerate(colors)]
        return {'version':'5.3.1','background':'#FFFFFF','objects':objects}
    if style == 'flag_horizontal':
        colors=spec['colors']; n=len(colors)
        objects=[rect(0,i*600/n,900,600/n,c) for i,c in enumerate(colors)]
        return {'version':'5.3.1','background':'#FFFFFF','objects':objects}
    if style == 'flag_disc':
        return {'version':'5.3.1','background':'#FFFFFF','objects':[circle(270,120,180,spec['colors'][1])]}
    bg=spec['bg']; accent=spec['accent']; t=spec['title']; sub=spec['subtitle']
    objs=[]
    if style=='frame':
        objs=[rect(52,48,796,500,'transparent',accent,11,8),rect(320,134,260,12,accent),
              textbox(t,88,190,724,69), textbox(sub,125,360,650,30,accent,bold=False)]
    elif style=='ribbon':
        objs=[rect(0,68,900,160,accent),rect(75,267,750,7,accent),
              textbox(t,60,108,780,77,bg),textbox(sub,115,325,670,39),
              rect(180,472,540,12,accent)]
    elif style=='badge':
        objs=[circle(264,65,190,accent),textbox(t,242,195,416,63,bg),
              textbox(sub,100,493,700,29),rect(170,447,560,6,'#FFFFFF')]
    elif style=='stripe':
        objs=[rect(0,0,165,600,accent),rect(207,113,590,10,accent),
              textbox(t,225,175,610,67,'#FFFFFF','left'),
              textbox(sub,225,345,610,31,accent,'left',False),rect(207,474,590,10,accent)]
    elif style=='split':
        objs=[rect(0,0,410,600,accent),textbox(t,35,195,340,62,bg),
              textbox(sub,465,230,385,41,'#FFFFFF'),rect(458,390,325,9,accent)]
    elif style=='corners':
        objs=[rect(0,0,260,50,accent),rect(640,550,260,50,accent),
              rect(45,100,810,6,accent),textbox(t,100,195,700,59),
              textbox(sub,120,357,660,32,accent,bold=False),rect(45,493,810,6,accent)]
    else:
        raise ValueError('Unknown style: '+style)
    return {'version':'5.3.1','background':bg,'objects':objs}

def wrap_text(text, draw, font, max_width):
    words = text.split()
    lines=[]; line=''
    for word in words:
        candidate=(line+' '+word).strip()
        if line and draw.textbbox((0,0),candidate,font=font)[2] > max_width:
            lines.append(line);line=word
        else: line=candidate
    if line:lines.append(line)
    return lines

def render(canvas, path):
    image=Image.new('RGB',(W,H),canvas.get('background','#FFFFFF'))
    draw=ImageDraw.Draw(image)
    for obj in canvas['objects']:
        typ=obj['type']; x=obj['left'];y=obj['top']
        if typ=='rect':
            fill=obj.get('fill','transparent')
            stroke=obj.get('stroke')
            bbox=(round(x),round(y),round(x+obj['width']),round(y+obj['height']))
            if fill not in ('transparent','none',None): draw.rectangle(bbox,fill=fill)
            if stroke:draw.rectangle(bbox,outline=stroke,width=int(obj.get('strokeWidth',1)))
        elif typ=='circle':
            r=obj['radius'];draw.ellipse((round(x),round(y),round(x+2*r),round(y+2*r)),fill=obj['fill'])
        elif typ=='textbox':
            max_width=obj['width'];sz=int(obj['fontSize']);bold=obj.get('fontWeight')=='bold'
            for trial in range(sz,23,-1):
                font=ImageFont.truetype(FONT_BOLD if bold else FONT_REGULAR,trial)
                lines=wrap_text(obj['text'],draw,font,max_width)
                if max(draw.textbbox((0,0),line,font=font)[2] for line in lines) <= max_width and len(lines)*trial*1.17 < 220:break
            lh=round(trial*1.2)
            for i,line in enumerate(lines):
                bbox=draw.textbbox((0,0),line,font=font)
                text_w=bbox[2]-bbox[0]
                left=x+((max_width-text_w)/2 if obj.get('textAlign')=='center' else 0)
                draw.text((round(left),round(y+i*lh)),line,font=font,fill=obj['fill'])
    path.parent.mkdir(parents=True, exist_ok=True)
    image.resize((720,480), Image.Resampling.LANCZOS).save(path, optimize=True)

if __name__=='__main__':
    manifest={}
    for product,presets in PRESETS.items():
        manifest[product]=[]
        for spec in presets:
            key=spec['key'];canvas=build_canvas(spec)
            (SEEDS/(key+'.json')).write_text(json.dumps(canvas,separators=(',',':'),ensure_ascii=False)+'\n',encoding='utf-8')
            render(canvas,THUMBS/(key+'.png'))
            manifest[product].append({k:spec[k] for k in ('key','name','category')})
    (ROOT/'assets'/'seed-presets.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print('generated',sum(map(len,PRESETS.values())),'templates and previews')
