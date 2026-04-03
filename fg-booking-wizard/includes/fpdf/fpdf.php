<?php
/*******************************************************************************
* FPDF 1.86 — Public Domain                                                    *
* Author: Olivier PLATHEY                                                       *
* Bundled with FG Booking Wizard — zero external dependencies.                 *
*******************************************************************************/
define('FPDF_VERSION','1.86');

class FPDF
{
protected $page,$n,$offsets,$buffer,$pages,$state,$compress,$k;
protected $DefOrientation,$CurOrientation,$StdPageSizes,$DefPageSize,$CurPageSize,$CurRotation,$PageInfo;
protected $wPt,$hPt,$w,$h,$lMargin,$tMargin,$rMargin,$bMargin,$cMargin,$x,$y,$lasth,$LineWidth;
protected $fontpath,$CoreFonts,$fonts,$FontFiles,$encodings,$cmaps;
protected $FontFamily,$FontStyle,$underline,$CurrentFont,$FontSizePt,$FontSize;
protected $DrawColor,$FillColor,$TextColor,$ColorFlag,$WithAlpha,$ws;
protected $images,$PageLinks,$links,$AutoPageBreak,$PageBreakTrigger;
protected $InHeader,$InFooter,$AliasNbPages,$ZoomMode,$LayoutMode,$metadata,$PDFVersion;

function __construct($orientation='P',$unit='mm',$size='A4')
{
    $this->state=0;$this->page=0;$this->n=2;$this->buffer='';
    $this->pages=[];$this->PageInfo=[];$this->fonts=[];$this->FontFiles=[];
    $this->images=[];$this->links=[];$this->InHeader=false;$this->InFooter=false;
    $this->lasth=0;$this->FontFamily='';$this->FontStyle='';$this->FontSizePt=12;
    $this->underline=false;$this->DrawColor='0 G';$this->FillColor='0 g';
    $this->TextColor='0 g';$this->ColorFlag=false;$this->WithAlpha=false;$this->ws=0;
    $this->fontpath=defined('FPDF_FONTPATH')?FPDF_FONTPATH:(is_dir(__DIR__.'/font')?__DIR__.'/font/':'');
    $this->CoreFonts=['courier','helvetica','times','symbol','zapfdingbats'];
    $this->k=match($unit){'pt'=>1,'mm'=>72/25.4,'cm'=>72/2.54,'in'=>72,default=>$this->Error('Incorrect unit: '.$unit)};
    $this->StdPageSizes=['a3'=>[841.89,1190.55],'a4'=>[595.28,841.89],'a5'=>[420.94,595.28],'letter'=>[612,792],'legal'=>[612,1008]];
    $size=$this->_getpagesize($size);$this->DefPageSize=$size;$this->CurPageSize=$size;
    $orientation=strtolower($orientation);
    if($orientation=='p'||$orientation=='portrait'){$this->DefOrientation='P';$this->w=$size[0];$this->h=$size[1];}
    elseif($orientation=='l'||$orientation=='landscape'){$this->DefOrientation='L';$this->w=$size[1];$this->h=$size[0];}
    else $this->Error('Incorrect orientation: '.$orientation);
    $this->CurOrientation=$this->DefOrientation;$this->wPt=$this->w*$this->k;$this->hPt=$this->h*$this->k;
    $this->CurRotation=0;$margin=28.35/$this->k;$this->SetMargins($margin,$margin);
    $this->cMargin=$margin/10;$this->LineWidth=.567/$this->k;
    $this->SetAutoPageBreak(true,2*$margin);$this->SetDisplayMode('default');
    $this->SetCompression(true);$this->PDFVersion='1.3';$this->metadata=[];
}
function SetMargins($l,$t,$r=-1){$this->lMargin=$l;$this->tMargin=$t;$this->rMargin=$r==-1?$l:$r;}
function SetLeftMargin($m){$this->lMargin=$m;if($this->page>0&&$this->x<$m)$this->x=$m;}
function SetTopMargin($m){$this->tMargin=$m;}
function SetRightMargin($m){$this->rMargin=$m;}
function SetAutoPageBreak($a,$m=0){$this->AutoPageBreak=$a;$this->bMargin=$m;$this->PageBreakTrigger=$this->h-$m;}
function SetDisplayMode($z,$l='default'){$this->ZoomMode=$z;$this->LayoutMode=$l;}
function SetCompression($c){$this->compress=$c;}
function SetTitle($t,$u=false){$this->metadata['Title']=$u?$t:$this->_toUTF8($t);}
function SetAuthor($a,$u=false){$this->metadata['Author']=$u?$a:$this->_toUTF8($a);}
function SetCreator($c,$u=false){$this->metadata['Creator']=$u?$c:$this->_toUTF8($c);}
function AliasNbPages($a='{nb}'){$this->AliasNbPages=$a;}
function Error($m){throw new Exception('FPDF error: '.$m);}

function Close()
{
    if($this->state==3)return;
    if($this->page==0)$this->AddPage();
    $this->InFooter=true;$this->Footer();$this->InFooter=false;
    $this->_endpage();$this->_enddoc();
}

function AddPage($orientation='',$size='',$rotation=0)
{
    if($this->state==3)$this->Error('Document is closed');
    $family=$this->FontFamily;$style=$this->FontStyle.($this->underline?'U':'');
    $fontsize=$this->FontSizePt;$lw=$this->LineWidth;$dc=$this->DrawColor;
    $fc=$this->FillColor;$tc=$this->TextColor;$cf=$this->ColorFlag;
    if($this->page>0){$this->InFooter=true;$this->Footer();$this->InFooter=false;$this->_endpage();}
    $this->_beginpage($orientation,$size,$rotation);
    $this->_out('2 J');$this->LineWidth=$lw;$this->_out(sprintf('%.2F w',$lw*$this->k));
    if($family)$this->SetFont($family,$style,$fontsize);
    $this->DrawColor=$dc;if($dc!='0 G')$this->_out($dc);
    $this->FillColor=$fc;if($fc!='0 g')$this->_out($fc);
    $this->TextColor=$tc;$this->ColorFlag=$cf;$this->ws=0;
    $this->InHeader=true;$this->Header();$this->InHeader=false;
    if($this->lasth==0)$this->lasth=$this->FontSize*1.5;
}
function Header(){}
function Footer(){}
function PageNo(){return $this->page;}

function SetDrawColor($r,$g=-1,$b=-1)
{
    $this->DrawColor=($r==0&&$g==0&&$b==0)||$g==-1?sprintf('%.3F G',$r/255):sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
    if($this->page>0)$this->_out($this->DrawColor);
}
function SetFillColor($r,$g=-1,$b=-1)
{
    $this->FillColor=($r==0&&$g==0&&$b==0)||$g==-1?sprintf('%.3F g',$r/255):sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag=($this->FillColor!=$this->TextColor);
    if($this->page>0)$this->_out($this->FillColor);
}
function SetTextColor($r,$g=-1,$b=-1)
{
    $this->TextColor=($r==0&&$g==0&&$b==0)||$g==-1?sprintf('%.3F g',$r/255):sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag=($this->FillColor!=$this->TextColor);
}
function GetStringWidth($s)
{
    $s=(string)$s;$cw=&$this->CurrentFont['cw'];$w=0;
    for($i=0,$l=strlen($s);$i<$l;$i++)$w+=$cw[ord($s[$i])]??500;
    return $w*$this->FontSize/1000;
}
function SetLineWidth($w){$this->LineWidth=$w;if($this->page>0)$this->_out(sprintf('%.2F w',$w*$this->k));}
function Line($x1,$y1,$x2,$y2){$this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));}
function Rect($x,$y,$w,$h,$style='')
{
    $op=$style=='F'?'f':($style=='FD'||$style=='DF'?'B':'S');
    $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
}
function SetFont($family,$style='',$size=0)
{
    if($family=='')$family=$this->FontFamily;else $family=strtolower($family);
    $style=strtoupper($style);
    if(strpos($style,'U')!==false){$this->underline=true;$style=str_replace('U','',$style);}else $this->underline=false;
    if($style=='IB')$style='BI';if($size==0)$size=$this->FontSizePt;
    if($this->FontFamily==$family&&$this->FontStyle==$style&&$this->FontSizePt==$size)return;
    $fontkey=$family.$style;
    if(!isset($this->fonts[$fontkey])){
        if(in_array($family,$this->CoreFonts))
            $this->fonts[$fontkey]=['i'=>count($this->fonts)+1,'type'=>'core','name'=>$this->_corename($family,$style),'up'=>-100,'ut'=>50,'cw'=>$this->_corecw()];
        else $this->Error('Undefined font: '.$family.' '.$style);
    }
    $this->FontFamily=$family;$this->FontStyle=$style;$this->FontSizePt=$size;
    $this->FontSize=$size/$this->k;$this->CurrentFont=&$this->fonts[$fontkey];
    if($this->page>0)$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}
function SetFontSize($s)
{
    if($this->FontSizePt==$s)return;$this->FontSizePt=$s;$this->FontSize=$s/$this->k;
    if($this->page>0)$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}
function AddLink(){$n=count($this->links)+1;$this->links[$n]=[0,0];return $n;}
function SetLink($link,$y=0,$page=-1){if($y==-1)$y=$this->y;if($page==-1)$page=$this->page;$this->links[$link]=[$page,$y];}
function Link($x,$y,$w,$h,$link){$this->PageLinks[$this->page][]=[$x*$this->k,$this->hPt-$y*$this->k,$w*$this->k,$h*$this->k,$link];}

function AcceptPageBreak(){return $this->AutoPageBreak;}

function Cell($w,$h=0,$txt='',$border=0,$ln=0,$align='',$fill=false,$link='')
{
    if(!isset($this->CurrentFont))$this->Error('No font set');
    $k=$this->k;
    if($this->y+$h>$this->PageBreakTrigger&&!$this->InHeader&&!$this->InFooter&&$this->AcceptPageBreak()){
        $x=$this->x;$ws=$this->ws;
        if($ws>0){$this->ws=0;$this->_out('0 Tw');}
        $this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);$this->x=$x;
        if($ws>0){$this->ws=$ws;$this->_out(sprintf('%.3F Tw',$ws*$k));}
    }
    if($w==0)$w=$this->w-$this->rMargin-$this->x;
    $s='';
    if($fill||$border==1){
        $op=$fill?($border==1?'B':'f'):'S';
        $s=sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
    }
    if(is_string($border)){
        $x=$this->x;$y=$this->y;
        if(strpos($border,'L')!==false)$s.=sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'T')!==false)$s.=sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
        if(strpos($border,'R')!==false)$s.=sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'B')!==false)$s.=sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
    }
    if($txt!==''){
        if($align=='R')$dx=$w-$this->cMargin-$this->GetStringWidth($txt);
        elseif($align=='C')$dx=($w-$this->GetStringWidth($txt))/2;
        else $dx=$this->cMargin;
        if($this->ColorFlag)$s.='q '.$this->TextColor.' ';
        $s.=sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
        if($this->underline)$s.=' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
        if($this->ColorFlag)$s.=' Q';
        if($link)$this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
    }
    if($s)$this->_out($s);
    $this->lasth=$h;
    if($ln>0){$this->y+=$h;if($ln==1)$this->x=$this->lMargin;}
    else $this->x+=$w;
}

function MultiCell($w,$h,$txt,$border=0,$align='J',$fill=false)
{
    if(!isset($this->CurrentFont))$this->Error('No font set');
    $cw=&$this->CurrentFont['cw'];
    if($w==0)$w=$this->w-$this->rMargin-$this->x;
    $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
    $s=str_replace("\r",'',(string)$txt);$nb=strlen($s);
    if($nb>0&&$s[$nb-1]=="\n")$nb--;
    $b=0;
    if($border){
        if($border==1){$border='LTRB';$b='LRT';$b2='LR';}
        else{$b2='';if(strpos($border,'L')!==false)$b2.='L';if(strpos($border,'R')!==false)$b2.='R';$b=strpos($border,'T')!==false?$b2.'T':$b2;}
    }
    $sep=-1;$i=0;$j=0;$l=0;$ns=0;$nl=1;
    while($i<$nb){
        $c=$s[$i];
        if($c=="\n"){
            if($this->ws>0){$this->ws=0;$this->_out('0 Tw');}
            $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            $i++;$sep=-1;$j=$i;$l=0;$ns=0;$nl++;
            if($border&&$nl==2)$b=$b2;continue;
        }
        if($c==' '){$sep=$i;$ls=$l;$ns++;}
        $l+=isset($cw[ord($c)])?$cw[ord($c)]:500;
        if($l>$wmax){
            if($sep==-1){
                if($i==$j)$i++;
                if($this->ws>0){$this->ws=0;$this->_out('0 Tw');}
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            } else {
                if($align=='J'){$this->ws=$ns>1?($wmax-$ls)/1000*$this->FontSize/($ns-1):0;$this->_out(sprintf('%.3F Tw',$this->ws*$this->k));}
                $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);$i=$sep+1;
            }
            $sep=-1;$j=$i;$l=0;$ns=0;$nl++;if($border&&$nl==2)$b=$b2;
        } else $i++;
    }
    if($this->ws>0){$this->ws=0;$this->_out('0 Tw');}
    if($border&&strpos($border,'B')!==false)$b.='B';
    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);$this->x=$this->lMargin;
}

function Ln($h=null){$this->x=$this->lMargin;$this->y+=$h??$this->lasth;}

function Image($file,$x=null,$y=null,$w=0,$h=0,$type='',$link='')
{
    if($file=='')$this->Error('Image file name empty');
    if(!isset($this->images[$file])){
        if($type==''){$pos=strrpos($file,'.');if(!$pos)$this->Error('No extension: '.$file);$type=substr($file,$pos+1);}
        $type=strtolower($type);if($type=='jpeg')$type='jpg';
        $mtd='_parse'.$type;if(!method_exists($this,$mtd))$this->Error('Unsupported image type: '.$type);
        $info=$this->$mtd($file);$info['i']=count($this->images)+1;$this->images[$file]=$info;
    } else $info=$this->images[$file];
    if($w==0&&$h==0){$w=-96;$h=-96;}
    if($w<0)$w=-$info['w']*72/$w/$this->k;if($h<0)$h=-$info['h']*72/$h/$this->k;
    if($w==0)$w=$h*$info['w']/$info['h'];if($h==0)$h=$w*$info['h']/$info['w'];
    if($y===null){
        if($this->y+$h>$this->PageBreakTrigger&&!$this->InHeader&&!$this->InFooter&&$this->AcceptPageBreak()){
            $x2=$this->x;$this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);$this->x=$x2;
        }
        $y=$this->y;$this->y+=$h;
    }
    if($x===null)$x=$this->x;
    $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',$w*$this->k,$h*$this->k,$x*$this->k,($this->h-($y+$h))*$this->k,$info['i']));
    if($link)$this->Link($x,$y,$w,$h,$link);
}

function GetPageWidth(){return $this->w;}
function GetPageHeight(){return $this->h;}
function GetX(){return $this->x;}
function SetX($x){$this->x=$x>=0?$x:$this->w+$x;}
function GetY(){return $this->y;}
function SetY($y,$resetX=true){$this->y=$y>=0?$y:$this->h+$y;if($resetX)$this->x=$this->lMargin;}
function SetXY($x,$y){$this->SetX($x);$this->SetY($y,false);}

function Output($dest='',$name='',$isUTF8=false)
{
    $this->Close();
    $dest=$dest==''?'I':strtoupper($dest);
    if($dest=='F'){if(!file_put_contents($name,$this->buffer))$this->Error('Cannot write: '.$name);}
    elseif($dest=='S'){return $this->buffer;}
    elseif($dest=='I'||$dest=='D'){
        header('Content-Type: application/pdf');
        header('Content-Length: '.strlen($this->buffer));
        header('Content-Disposition: '.($dest=='D'?'attachment':'inline').'; filename="'.(basename($name)?basename($name):'doc.pdf').'"');
        echo $this->buffer;
    }
    return '';
}

// ── Internal ─────────────────────────────────────────────────────────────────
protected function _getpagesize($size)
{
    if(is_string($size)){
        $size=strtolower($size);if(!isset($this->StdPageSizes[$size]))$this->Error('Unknown page size: '.$size);
        $a=$this->StdPageSizes[$size];return [$a[0]/$this->k,$a[1]/$this->k];
    }
    return $size[0]>$size[1]?[$size[1],$size[0]]:[$size[0],$size[1]];
}
protected function _beginpage($orientation,$size,$rotation)
{
    $this->page++;$this->pages[$this->page]='';$this->PageLinks[$this->page]=[];
    $this->state=2;$this->x=$this->lMargin;$this->y=$this->tMargin;$this->lasth=0;$this->FontFamily='';
    $this->SetFont('helvetica','',10);
    if($orientation=='')$orientation=$this->DefOrientation;else $orientation=strtoupper($orientation[0]);
    if($size=='')$size=$this->DefPageSize;else $size=$this->_getpagesize($size);
    if($orientation!=$this->CurOrientation||$size[0]!=$this->CurPageSize[0]||$size[1]!=$this->CurPageSize[1]){
        if($orientation=='P'){$this->w=$size[0];$this->h=$size[1];}else{$this->w=$size[1];$this->h=$size[0];}
        $this->wPt=$this->w*$this->k;$this->hPt=$this->h*$this->k;
        $this->PageBreakTrigger=$this->h-$this->bMargin;$this->CurOrientation=$orientation;$this->CurPageSize=$size;
    }
    if($orientation!=$this->DefOrientation||$size[0]!=$this->DefPageSize[0]||$size[1]!=$this->DefPageSize[1])
        $this->PageInfo[$this->page]['size']=[$this->wPt,$this->hPt];
    if($rotation!=0){if($rotation%90!=0)$this->Error('Bad rotation: '.$rotation);$this->CurRotation=$rotation;$this->PageInfo[$this->page]['rotation']=$rotation;}
}
protected function _endpage(){$this->state=1;}
protected function _escape($s){return str_replace(['\\',')','(',"\r"],['\\\\','\\)','\\(','\\r'],$s);}
protected function _textstring($s){if(!$this->_isascii($s))$s=$this->_UTF8toUTF16($s);return '('.$this->_escape($s).')';}
protected function _isascii($s){for($i=0,$n=strlen($s);$i<$n;$i++)if(ord($s[$i])>127)return false;return true;}
protected function _UTF8toUTF16($s)
{
    $res="\xFE\xFF";$nb=strlen($s);$i=0;
    while($i<$nb){
        $c1=ord($s[$i++]);
        if($c1>=224){$c2=ord($s[$i++]);$c3=ord($s[$i++]);$res.=chr((($c1&0x0F)<<4)|(($c2&0x3C)>>2)).chr((($c2&0x03)<<6)|($c3&0x3F));}
        elseif($c1>=192){$c2=ord($s[$i++]);$res.=chr(($c1&0x1C)>>2).chr((($c1&0x03)<<6)|($c2&0x3F));}
        else $res.="\x00".chr($c1);
    }
    return $res;
}
protected function _dounderline($x,$y,$txt)
{
    $up=$this->CurrentFont['up'];$ut=$this->CurrentFont['ut'];$w=$this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
    return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
}
protected function _parsejpg($file)
{
    $a=getimagesize($file);if(!$a)$this->Error('Missing image: '.$file);
    if($a[2]!=2)$this->Error('Not a JPEG: '.$file);
    $cs=!isset($a['channels'])||$a['channels']==3?'DeviceRGB':($a['channels']==4?'DeviceCMYK':'DeviceGray');
    return ['w'=>$a[0],'h'=>$a[1],'cs'=>$cs,'bpc'=>$a['bits']??8,'f'=>'DCTDecode','data'=>file_get_contents($file)];
}
protected function _parsepng($file)
{
    $f=fopen($file,'rb');if(!$f)$this->Error('Cannot open: '.$file);
    $info=$this->_parsepngstream($f,$file);fclose($f);return $info;
}
protected function _parsepngstream($f,$file)
{
    if($this->_rs($f,8)!="\x89PNG\r\n\x1a\n")$this->Error('Not a PNG: '.$file);
    $this->_rs($f,4);if($this->_rs($f,4)!='IHDR')$this->Error('Bad PNG: '.$file);
    $w=$this->_ri($f);$h=$this->_ri($f);$bpc=ord($this->_rs($f,1));if($bpc>8)$this->Error('16-bit PNG unsupported: '.$file);
    $ct=ord($this->_rs($f,1));
    $cs=match($ct){0,4=>'DeviceGray',2,6=>'DeviceRGB',3=>'Indexed',default=>$this->Error('Unknown PNG color type')};
    if(ord($this->_rs($f,1))!=0)$this->Error('Unknown PNG compression');
    if(ord($this->_rs($f,1))!=0)$this->Error('Unknown PNG filter');
    if(ord($this->_rs($f,1))!=0)$this->Error('PNG interlacing unsupported');
    $this->_rs($f,4);
    $dp='/Predictor 15 /Colors '.($cs=='DeviceRGB'?3:1).' /BitsPerComponent '.$bpc.' /Columns '.$w;
    $pal='';$trns='';$data='';
    do{
        $n=$this->_ri($f);$type=$this->_rs($f,4);
        if($type=='PLTE'){$pal=$this->_rs($f,$n);$this->_rs($f,4);}
        elseif($type=='tRNS'){
            $t=$this->_rs($f,$n);
            if($ct==0)$trns=[ord(substr($t,1,1))];
            elseif($ct==2)$trns=[ord(substr($t,1,1)),ord(substr($t,3,1)),ord(substr($t,5,1))];
            else{$pos=strpos($t,"\x00");if($pos!==false)$trns=[$pos];}
            $this->_rs($f,4);
        }
        elseif($type=='IDAT'){$data.=$this->_rs($f,$n);$this->_rs($f,4);}
        elseif($type=='IEND')break;
        else $this->_rs($f,$n+4);
    }while($n);
    if($cs=='Indexed'&&empty($pal))$this->Error('Missing PNG palette');
    $info=['w'=>$w,'h'=>$h,'cs'=>$cs,'bpc'=>$bpc,'f'=>'FlateDecode','dp'=>$dp,'pal'=>$pal,'trns'=>$trns];
    if($ct>=4){
        if(!function_exists('gzuncompress'))$this->Error('Zlib needed for PNG alpha');
        $data=gzuncompress($data);$color='';$alpha='';
        if($ct==4){$len=2*$w;for($i=0;$i<$h;$i++){$pos=(1+$len)*$i;$color.=$data[$pos];$alpha.=$data[$pos];$line=substr($data,$pos+1,$len);$color.=preg_replace('/(.)./s','$1',$line);$alpha.=preg_replace('/.(.)/s','$1',$line);}}
        else{$len=4*$w;for($i=0;$i<$h;$i++){$pos=(1+$len)*$i;$color.=$data[$pos];$alpha.=$data[$pos];$line=substr($data,$pos+1,$len);$color.=preg_replace('/(..)(..)/s','$1',$line);$alpha.=preg_replace('/(..)(..)/s','$2',$line);}}
        unset($data);$data=gzcompress($color);$info['smask']=gzcompress($alpha);$this->WithAlpha=true;if($this->PDFVersion<'1.4')$this->PDFVersion='1.4';
    }
    $info['data']=$data;return $info;
}
protected function _rs($f,$n){$r='';while($n>0&&!feof($f)){$s=fread($f,$n);if($s===false)$this->Error('Read error');$n-=strlen($s);$r.=$s;}if($n>0)$this->Error('Unexpected EOF');return $r;}
protected function _ri($f){$a=unpack('Ni',$this->_rs($f,4));return $a['i'];}
protected function _out($s){if($this->state==2)$this->pages[$this->page].=$s."\n";else $this->buffer.=$s."\n";}
protected function _getoffset(){return strlen($this->buffer);}
protected function _newobj($n=null){if($n===null)$n=++$this->n;$this->offsets[$n]=$this->_getoffset();$this->_out($n.' 0 obj');return $n;}
protected function _putstream($data){$this->_out('stream');$this->_out($data);$this->_out('endstream');}
protected function _putstreamobj($data){
    if($this->compress){$e='/Filter /FlateDecode ';$data=gzcompress($data);}else $e='';
    $e.='/Length '.strlen($data);$this->_newobj();$this->_out('<<'.$e.'>>');$this->_putstream($data);$this->_out('endobj');
}
protected function _putlinks($n)
{
    foreach($this->PageLinks[$n] as $pl){
        $this->_newobj();$rect=sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
        $s='<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
        if(is_string($pl[4]))$s.='/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
        else{$l=$this->links[$pl[4]];$h=isset($this->PageInfo[$l[0]]['size'])?$this->PageInfo[$l[0]]['size'][1]:(($this->DefOrientation=='P')?$this->DefPageSize[1]*$this->k:$this->DefPageSize[0]*$this->k);$s.=sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',isset($this->PageInfo[$l[0]]['n'])?$this->PageInfo[$l[0]]['n']:2+2*($l[0]-1),$h-$l[1]*$this->k);}
        $this->_out($s);$this->_out('endobj');
    }
}
protected function _putpage($n)
{
    $this->_newobj();$this->PageInfo[$n]['n']=$this->n;
    $this->_out('<</Type /Page /Parent 1 0 R');
    if(isset($this->PageInfo[$n]['size']))$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageInfo[$n]['size'][0],$this->PageInfo[$n]['size'][1]));
    if(isset($this->PageInfo[$n]['rotation']))$this->_out('/Rotate '.$this->PageInfo[$n]['rotation']);
    $this->_out('/Resources 2 0 R');
    if(!empty($this->PageLinks[$n])){$s='/Annots [';foreach($this->PageLinks[$n] as $pl)$s.=$pl[5].' 0 R ';$this->_out($s.']');}
    if($this->WithAlpha)$this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
    $this->_out('/Contents '.($this->n+1).' 0 R>>');$this->_out('endobj');
    if(!empty($this->AliasNbPages))$this->pages[$n]=str_replace($this->AliasNbPages,$this->page,$this->pages[$n]);
    $this->_putstreamobj($this->pages[$n]);$this->_putlinks($n);
}
protected function _putpages()
{
    $nb=$this->page;for($n=1;$n<=$nb;$n++)$this->_putpage($n);
    $this->offsets[1]=$this->_getoffset();$this->_out('1 0 obj');$this->_out('<</Type /Pages');
    $kids='/Kids [';for($n=1;$n<=$nb;$n++)$kids.=$this->PageInfo[$n]['n'].' 0 R ';$this->_out($kids.']');
    $this->_out('/Count '.$nb);
    $w=$this->DefOrientation=='P'?$this->DefPageSize[0]:$this->DefPageSize[1];
    $h=$this->DefOrientation=='P'?$this->DefPageSize[1]:$this->DefPageSize[0];
    $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$w*$this->k,$h*$this->k));$this->_out('>>');$this->_out('endobj');
}
protected function _putfonts()
{
    foreach($this->fonts as $k=>$font){
        $this->_newobj();$this->fonts[$k]['n']=$this->n;
        $this->_out('<</Type /Font /Subtype /Type1 /BaseFont /'.$font['name'].' /Encoding /WinAnsiEncoding>>');$this->_out('endobj');
    }
}
protected function _putimages(){foreach($this->images as $file=>&$info){$this->_putimage($info);unset($info['data'],$info['smask']);}unset($info);}
protected function _putimage(&$info)
{
    $this->_newobj();$info['n']=$this->n;
    $this->_out('<</Type /XObject /Subtype /Image /Width '.$info['w'].' /Height '.$info['h']);
    if($info['cs']=='Indexed')$this->_out('/ColorSpace [/Indexed /DeviceRGB '.(strlen($info['pal'])/3-1).' '.($this->n+1).' 0 R]');
    else{$this->_out('/ColorSpace /'.$info['cs']);if($info['cs']=='DeviceCMYK')$this->_out('/Decode [1 0 1 0 1 0 1 0]');}
    $this->_out('/BitsPerComponent '.$info['bpc']);
    if(isset($info['f']))$this->_out('/Filter /'.$info['f']);
    if(isset($info['dp']))$this->_out('/DecodeParms <<'.$info['dp'].'>>');
    if(isset($info['trns'])&&is_array($info['trns'])){$trns='';foreach($info['trns'] as $t)$trns.=$t.' '.$t.' ';$this->_out('/Mask ['.$trns.']');}
    if(isset($info['smask']))$this->_out('/SMask '.($this->n+1).' 0 R');
    $this->_out('/Length '.strlen($info['data']).'>>');$this->_putstream($info['data']);$this->_out('endobj');
    if($info['cs']=='Indexed')$this->_putstreamobj($info['pal']);
    if(isset($info['smask'])){$dp='/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'];$smask_info=['w'=>$info['w'],'h'=>$info['h'],'cs'=>'DeviceGray','bpc'=>8,'f'=>'FlateDecode','dp'=>$dp,'data'=>$info['smask']];$this->_putimage($smask_info);}
}
protected function _putresourcedict()
{
    $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
    $this->_out('/Font <<');foreach($this->fonts as $f)$this->_out('/F'.$f['i'].' '.$f['n'].' 0 R');$this->_out('>>');
    $this->_out('/XObject <<');foreach($this->images as $img)$this->_out('/I'.$img['i'].' '.$img['n'].' 0 R');$this->_out('>>');
}
protected function _putresources()
{
    $this->_putfonts();$this->_putimages();
    $this->offsets[2]=$this->_getoffset();$this->_out('2 0 obj <<');$this->_putresourcedict();$this->_out('>> endobj');
}
protected function _putinfo()
{
    $this->metadata['Producer']='FPDF '.FPDF_VERSION;$this->metadata['CreationDate']='D:'.@date('YmdHis');
    foreach($this->metadata as $k=>$v)$this->_out('/'.$k.' '.$this->_textstring($v));
}
protected function _putcatalog()
{
    $n=$this->PageInfo[1]['n'];$this->_out('/Type /Catalog /Pages 1 0 R');
    if($this->ZoomMode=='fullpage')$this->_out('/OpenAction ['.$n.' 0 R /Fit]');
    elseif($this->ZoomMode=='fullwidth')$this->_out('/OpenAction ['.$n.' 0 R /FitH null]');
    elseif(!is_string($this->ZoomMode))$this->_out('/OpenAction ['.$n.' 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
    if($this->LayoutMode=='single')$this->_out('/PageLayout /SinglePage');
    elseif($this->LayoutMode=='continuous')$this->_out('/PageLayout /OneColumn');
}
protected function _enddoc()
{
    $this->_out('%PDF-'.$this->PDFVersion);$this->_putpages();$this->_putresources();
    $this->_newobj();$this->_out('<<');$this->_putinfo();$this->_out('>> endobj');
    $this->_newobj();$this->_out('<<');$this->_putcatalog();$this->_out('>> endobj');
    $offset=$this->_getoffset();$this->_out('xref');$this->_out('0 '.($this->n+1));$this->_out('0000000000 65535 f ');
    for($i=1;$i<=$this->n;$i++)$this->_out(sprintf('%010d 00000 n ',$this->offsets[$i]));
    $this->_out('trailer <<');$this->_out('/Size '.($this->n+1));$this->_out('/Root '.$this->n.' 0 R');$this->_out('/Info '.($this->n-1).' 0 R');$this->_out('>>');
    $this->_out('startxref');$this->_out($offset);$this->_out('%%EOF');$this->state=3;
}
protected function _toUTF8(string $s): string
{
    // utf8_encode() is deprecated in PHP 8.2. Use mb_convert_encoding if available,
    // otherwise fall back to iconv, otherwise return as-is (already UTF-8 in most cases).
    if(function_exists('mb_convert_encoding'))
        return mb_convert_encoding($s,'UTF-8','ISO-8859-1');
    if(function_exists('iconv'))
        return iconv('ISO-8859-1','UTF-8//IGNORE',$s);
    return $s;
}
protected function _corename($family,$style)
{
    $m=['courier'=>'Courier','courierB'=>'Courier-Bold','courierI'=>'Courier-Oblique','courierBI'=>'Courier-BoldOblique',
        'helvetica'=>'Helvetica','helveticaB'=>'Helvetica-Bold','helveticaI'=>'Helvetica-Oblique','helveticaBI'=>'Helvetica-BoldOblique',
        'times'=>'Times-Roman','timesB'=>'Times-Bold','timesI'=>'Times-Italic','timesBI'=>'Times-BoldItalic',
        'symbol'=>'Symbol','zapfdingbats'=>'ZapfDingbats'];
    return $m[$family.$style]??$m[$family]??'Helvetica';
}
protected function _corecw()
{
    // Helvetica character widths (standard PDF core font)
    $cw=array_fill(0,256,278);
    $w=[32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,
        64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,
        80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,
        96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,
        111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584];
    foreach($w as $c=>$v)$cw[$c]=$v;
    return $cw;
}
}
